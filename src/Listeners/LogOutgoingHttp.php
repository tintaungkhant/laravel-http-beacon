<?php

namespace HttpBeacon\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use HttpBeacon\Models\OutgoingRequest;
use HttpBeacon\RequestCollector;
use HttpBeacon\Support\Redactor;

class LogOutgoingHttp
{
    public function __construct(private RequestCollector $collector) {}

    public function handleResponse(ResponseReceived $event): void
    {
        if (! $this->shouldRecord($event->request)) {
            return;
        }

        $this->persist([
            'request_uuid' => $this->collector->getRequestUuid(),
            'hostname' => $this->hostname($event->request),
            'method' => $event->request->method(),
            'uri' => $event->request->url(),
            'status' => $event->response->status(),
            'duration_ms' => $this->duration($event->response),
            'payload' => $this->payload($event->request),
            'request_headers' => Redactor::headers($event->request->headers()),
            'response' => $this->responseBody($event->response),
            'response_headers' => Redactor::headers($event->response->headers()),
            'failed' => false,
        ]);
    }

    public function handleFailure(ConnectionFailed $event): void
    {
        if (! $this->shouldRecord($event->request)) {
            return;
        }

        $this->persist([
            'request_uuid' => $this->collector->getRequestUuid(),
            'hostname' => $this->hostname($event->request),
            'method' => $event->request->method(),
            'uri' => $event->request->url(),
            'payload' => $this->payload($event->request),
            'request_headers' => Redactor::headers($event->request->headers()),
            'error' => $this->errorDetails($event),
            'failed' => true,
        ]);
    }

    private function errorDetails(ConnectionFailed $event): ?array
    {
        $exception = $event->exception ?? null;

        if (! $exception) {
            return null;
        }

        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
        ];
    }

    private function shouldRecord(Request $request): bool
    {
        $host = $this->hostname($request);
        $ignore = (array) config('beacon.outgoing.ignore_hosts', []);

        if ($ignore && Str::is($ignore, $host)) {
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

    private function persist(array $attributes): void
    {
        $this->collector->pause();

        try {
            OutgoingRequest::create($attributes);
        } catch (\Throwable $e) {
            report($e);
        } finally {
            $this->collector->resume();
        }
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

        return Redactor::parameters($request->data());
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
                return Redactor::parameters($decoded);
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
        $limit = config('beacon.outgoing.body_size_limit_kb');

        if ($limit === null || $limit === 0) {
            return false;
        }

        return mb_strlen($content) / 1000 > $limit;
    }
}
