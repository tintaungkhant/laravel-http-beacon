<?php

namespace Tintaungkhant\TrafficMonitor\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogOutgoingHttp
{
    private const BODY_SIZE_LIMIT_KB = 64;

    public function handleResponse(ResponseReceived $event): void
    {
        Log::info('[traffic-monitor] outgoing http', [
            'time' => now()->toIso8601String(),
            'hostname' => $this->hostname($event->request),
            'method' => $event->request->method(),
            'uri' => $event->request->url(),
            'status' => $event->response->status(),
            'duration_ms' => $this->duration($event->response),
            'payload' => $this->payload($event->request),
            'request_headers' => $this->formatHeaders($event->request->headers()),
            'response' => $this->responseBody($event->response),
            'response_headers' => $this->formatHeaders($event->response->headers()),
        ]);
    }

    public function handleFailure(ConnectionFailed $event): void
    {
        Log::warning('[traffic-monitor] outgoing http failed', [
            'time' => now()->toIso8601String(),
            'hostname' => $this->hostname($event->request),
            'method' => $event->request->method(),
            'uri' => $event->request->url(),
            'payload' => $this->payload($event->request),
            'request_headers' => $this->formatHeaders($event->request->headers()),
        ]);
    }

    private function hostname(Request $request): string
    {
        return $request->toPsrRequest()->getUri()->getHost();
    }

    private function duration(Response $response): ?int
    {
        $stats = $response->transferStats ?? null;

        if ($stats && $stats->getTransferTime()) {
            return (int) floor($stats->getTransferTime() * 1000);
        }

        return null;
    }

    private function payload(Request $request): array|string
    {
        if ($request->isMultipart()) {
            return collect($request->data())
                ->mapWithKeys(fn ($part) => [$part['name'] => '<multipart>'])
                ->all();
        }

        return $request->data();
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
        $content = $response->body();

        if ($content === '') {
            return 'Empty Response';
        }

        if ($this->exceedsLimit($content)) {
            return 'Truncated';
        }

        $contentType = strtolower($response->header('Content-Type') ?? '');

        if (Str::contains($contentType, 'application/json')) {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        if (Str::startsWith($contentType, 'text/')) {
            return $content;
        }

        if ($response->redirect()) {
            return 'Redirected to '.$response->header('Location');
        }

        return 'Binary or non-text response';
    }

    private function exceedsLimit(string $content): bool
    {
        return mb_strlen($content) / 1000 > self::BODY_SIZE_LIMIT_KB;
    }
}
