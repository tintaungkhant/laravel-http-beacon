<?php

namespace HttpBeacon\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;
use HttpBeacon\Beacon;
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
        if (! Beacon::isRecording()) {
            return false;
        }

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
        if (! $request->isMultipart()) {
            return Redactor::parameters($request->data());
        }

        $extracted = collect($request->data())->mapWithKeys(function ($part) {
            if ($part['contents'] instanceof File) {
                $value = [
                    'name' => $part['filename'] ?? $part['contents']->getClientOriginalName(),
                    'size' => ($part['contents']->getSize() / 1000).'KB',
                    'headers' => $part['headers'] ?? [],
                ];
            } elseif (is_resource($part['contents'])) {
                $filesize = @filesize(stream_get_meta_data($part['contents'])['uri']);
                $value = [
                    'name' => $part['filename'] ?? null,
                    'size' => $filesize ? ($filesize / 1000).'KB' : null,
                    'headers' => $part['headers'] ?? [],
                ];
            } elseif (json_encode($part['contents']) === false) {
                $value = [
                    'name' => $part['filename'] ?? null,
                    'size' => (strlen($part['contents']) / 1000).'KB',
                    'headers' => $part['headers'] ?? [],
                ];
            } else {
                $value = $part['contents'];
            }

            return [$part['name'] => $value];
        })->toArray();

        return Redactor::parameters($extracted);
    }

    private function responseBody(Response $response): mixed
    {
        $stream = $response->toPsrResponse()->getBody();

        if (! $stream->isSeekable()) {
            return 'Streamed Response';
        }

        if ($response->redirect()) {
            return 'Redirected to '.$response->header('Location');
        }

        $contentType = strtolower($response->header('Content-Type') ?? '');

        if ($this->isBinaryContentType($contentType)) {
            return 'Binary or non-text response';
        }

        $limit = $this->bodySizeLimitBytes();

        $declared = $response->header('Content-Length');
        if ($limit > 0 && $declared !== null && (int) $declared > $limit) {
            return 'Truncated';
        }

        $content = $this->readBoundedBody($stream, $limit);

        if ($content === null) {
            return 'Truncated';
        }

        if ($content === '') {
            return 'Empty Response';
        }

        if (Str::contains($contentType, 'application/json')) {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return Redactor::parameters($decoded);
            }
        }

        if (Str::startsWith($contentType, 'text/')) {
            return $content;
        }

        return 'Binary or non-text response';
    }

    private function readBoundedBody(\Psr\Http\Message\StreamInterface $stream, int $limit): ?string
    {
        $stream->rewind();

        if ($limit === 0) {
            $content = (string) $stream;
            $stream->rewind();

            return $content;
        }

        $content = '';

        while (! $stream->eof()) {
            $content .= $stream->read(8192);

            if (strlen($content) > $limit) {
                $stream->rewind();

                return null;
            }
        }

        $stream->rewind();

        return $content;
    }

    private function isBinaryContentType(string $contentType): bool
    {
        if ($contentType === '') {
            return false;
        }

        return Str::startsWith($contentType, [
            'image/',
            'video/',
            'audio/',
            'font/',
            'application/pdf',
            'application/zip',
            'application/x-tar',
            'application/x-gzip',
            'application/x-7z-compressed',
            'application/octet-stream',
        ]);
    }

    private function bodySizeLimitBytes(): int
    {
        $kb = (int) config('beacon.outgoing.body_size_limit_kb', 0);

        return $kb > 0 ? $kb * 1000 : 0;
    }
}
