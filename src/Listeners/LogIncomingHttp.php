<?php

namespace HttpBeacon\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use HttpBeacon\Models\IncomingRequest;
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
            IncomingRequest::create([
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
                'queries' => $collected['queries'],
                'models' => $collected['models'],
                'jobs' => $collected['jobs'],
            ]);
        } finally {
            $this->collector->resume();
        }
    }

    private function shouldRecord(RequestHandled $event): bool
    {
        $config = config('beacon.incoming');

        if (Str::is((array) ($config['ignore_paths'] ?? []), $event->request->path())) {
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
