<?php

namespace HttpBeacon\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\JobDispatch;
use HttpBeacon\Models\ModelTouch;
use HttpBeacon\Models\QueryRecord;
use HttpBeacon\RequestCollector;
use HttpBeacon\Support\Redactor;

class LogIncomingHttp
{
    public function __construct(private RequestCollector $collector) {}

    public function handle(RequestHandled $event): void
    {
        $collected = $this->collector->flush();

        if (! $this->shouldRecord($event)) {
            return;
        }

        $this->collector->pause();

        try {
            $incoming = IncomingRequest::create([
                'request_uuid' => $this->collector->getRequestUuid(),
                'hostname' => $event->request->getHost(),
                'method' => $event->request->method(),
                'controller_action' => $event->request->route()?->getActionName(),
                'middlewares' => $this->middlewares($event->request),
                'path' => $event->request->getRequestUri(),
                'status' => $event->response->getStatusCode(),
                'duration_ms' => $this->duration($event->request),
                'memory_mb' => $this->memoryMb(),
                'ip' => $event->request->ip(),
                'payload' => $this->payload($event->request),
                'request_headers' => Redactor::headers($event->request->headers->all()),
                'response' => $this->responseBody($event->response),
                'response_headers' => Redactor::headers($event->response->headers->all()),
                'query_count' => $collected['query_count'],
            ]);

            if ($collected['queries']) {
                QueryRecord::insert($this->buildQueryRows($incoming->id, $collected['queries']));
            }

            if ($collected['models']) {
                ModelTouch::insert($this->buildModelTouchRows($incoming->id, $collected['models']));
            }

            if ($collected['jobs']) {
                JobDispatch::insert($this->buildJobDispatchRows($incoming->id, $collected['jobs']));
            }
        } catch (\Throwable $e) {
            report($e);
        } finally {
            $this->collector->resume();
        }
    }

    private function shouldRecord(RequestHandled $event): bool
    {
        $config = config('beacon.incoming');
        $path = $event->request->path();

        $only = (array) ($config['only_paths'] ?? []);
        if (! empty($only) && ! Str::is($only, $path)) {
            return false;
        }

        if (Str::is((array) ($config['ignore_paths'] ?? []), $path)) {
            return false;
        }

        if (in_array(strtoupper($event->request->method()), array_map('strtoupper', (array) ($config['ignore_methods'] ?? [])), true)) {
            return false;
        }

        if (in_array($event->response->getStatusCode(), (array) ($config['ignore_status_codes'] ?? []), true)) {
            return false;
        }

        return $this->passesSampling();
    }

    private function passesSampling(): bool
    {
        $rate = (float) config('beacon.sampling_rate', 1.0);

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return mt_rand() / mt_getrandmax() < $rate;
    }

    private function memoryMb(): ?float
    {
        if (! config('beacon.collect.memory', true)) {
            return null;
        }

        return round(memory_get_peak_usage(true) / 1024 / 1024, 2);
    }

    private function buildQueryRows(int $requestId, array $queries): array
    {
        return array_map(fn ($q) => [
            'request_id' => $requestId,
            'connection' => $q['connection'],
            'type' => $this->extractQueryType($q['sql']),
            'sql' => $q['sql'],
            'sql_with_bindings' => $q['sql_with_bindings'],
            'bindings' => ! empty($q['bindings']) ? json_encode($q['bindings']) : null,
            'time_ms' => $q['time_ms'],
        ], $queries);
    }

    private function extractQueryType(string $sql): string
    {
        preg_match('/^\s*(\w+)/i', $sql, $matches);
        $type = strtoupper($matches[1] ?? '');

        return in_array($type, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'], true) ? $type : 'OTHER';
    }

    private function buildModelTouchRows(int $requestId, array $models): array
    {
        return array_map(fn ($m) => [
            'request_id' => $requestId,
            'model_class' => $m['class'],
            'model_id' => $m['id'] !== null ? (string) $m['id'] : null,
            'action' => $m['action'],
            'changes' => $m['changes'] !== null ? json_encode($m['changes']) : null,
        ], $models);
    }

    private function buildJobDispatchRows(int $requestId, array $jobs): array
    {
        return array_map(fn ($j) => [
            'request_id' => $requestId,
            'job_class' => $j['class'],
            'connection' => $j['connection'] ?? null,
            'queue' => $j['queue'] ?? null,
            'payload' => $j['payload'] !== null ? json_encode($j['payload']) : null,
        ], $jobs);
    }

    private function middlewares(Request $request): array
    {
        $route = $request->route();

        return $route ? array_values($route->gatherMiddleware()) : [];
    }

    private function duration(Request $request): ?int
    {
        $start = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT');

        if (! $start) {
            return null;
        }

        return (int) floor((microtime(true) - $start) * 1000);
    }

    private function payload(Request $request): array|string
    {
        if (Str::startsWith(strtolower($request->headers->get('Content-Type') ?? ''), 'text/plain')) {
            return (string) $request->getContent();
        }

        $files = $request->files->all();

        array_walk_recursive($files, function (&$file) {
            $file = [
                'name' => $file->getClientOriginalName(),
                'size' => $file->isFile() ? ($file->getSize() / 1000).'KB' : '0',
            ];
        });

        return Redactor::parameters(array_replace_recursive($request->input(), $files));
    }

    private function responseBody(Response $response): mixed
    {
        $content = $response->getContent();

        if ($content === false) {
            return 'Streamed Response';
        }

        if ($content === '') {
            return 'Empty Response';
        }

        if ($this->exceedsLimit($content)) {
            return 'Truncated';
        }

        $contentType = strtolower($response->headers->get('Content-Type') ?? '');

        if (Str::contains($contentType, 'application/json')) {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return Redactor::parameters($decoded);
            }
        }

        if (Str::startsWith($contentType, 'text/plain')) {
            return $content;
        }

        if ($response instanceof RedirectResponse) {
            return 'Redirected to '.$response->getTargetUrl();
        }

        return 'Binary or non-text response';
    }

    private function exceedsLimit(string $content): bool
    {
        $limit = config('beacon.incoming.body_size_limit_kb');

        if ($limit === null || $limit === 0) {
            return false;
        }

        return mb_strlen($content) / 1000 > $limit;
    }
}
