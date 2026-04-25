<?php

namespace Tintaungkhant\TrafficMonitor\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Tintaungkhant\TrafficMonitor\RequestCollector;

class LogIncomingHttp
{
    public function __construct(private RequestCollector $collector) {}

    public function handle(RequestHandled $event): void
    {
        $collected = $this->collector->flush();

        Log::info('[traffic-monitor] incoming http', [
            'time' => now()->toIso8601String(),
            'hostname' => $event->request->getHost(),
            'method' => $event->request->method(),
            'controller_action' => $event->request->route()?->getActionName(),
            'middlewares' => $this->middlewares($event->request),
            'path' => $event->request->getRequestUri(),
            'status' => $event->response->getStatusCode(),
            'duration_ms' => $this->duration($event->request),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'ip' => $event->request->ip(),
            'payload' => $this->payload($event->request),
            'request_headers' => $this->formatHeaders($event->request->headers->all()),
            'response' => $this->responseBody($event->response),
            'response_headers' => $this->formatHeaders($event->response->headers->all()),
            'queries' => $collected['queries'],
            'models' => $collected['models'],
            'jobs' => $collected['jobs'],
        ]);
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

        return array_replace_recursive($request->input(), $files);
    }

    private function formatHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $name => $values) {
            $result[strtolower($name)] = implode(', ', (array) $values);
        }

        return $result;
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
                return $decoded;
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
        $limit = config('traffic-monitor.incoming.body_size_limit_kb');

        if ($limit === null || $limit === 0) {
            return false;
        }

        return mb_strlen($content) / 1000 > $limit;
    }
}
