<?php

namespace HttpBeacon\Http\Controllers;

use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\OutgoingRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    private const WINDOW_HOURS = 24;

    public function index(): JsonResponse
    {
        $since = now()->subHours(self::WINDOW_HOURS);

        $incoming = IncomingRequest::query()->where('created_at', '>=', $since);
        $outgoing = OutgoingRequest::query()->where('created_at', '>=', $since);

        return response()->json([
            'data' => [
                'window_hours' => self::WINDOW_HOURS,
                'incoming' => [
                    'total' => (clone $incoming)->count(),
                    'avg_duration_ms' => (int) (clone $incoming)->avg('duration_ms'),
                    'status_buckets' => $this->statusBuckets(clone $incoming),
                    'slowest' => (clone $incoming)
                        ->orderByDesc('duration_ms')
                        ->limit(5)
                        ->get(['id', 'method', 'path', 'status', 'duration_ms', 'created_at']),
                ],
                'outgoing' => [
                    'total' => (clone $outgoing)->count(),
                    'failed' => (clone $outgoing)->where('failed', true)->count(),
                    'avg_duration_ms' => (int) (clone $outgoing)->avg('duration_ms'),
                    'slowest' => (clone $outgoing)
                        ->orderByDesc('duration_ms')
                        ->limit(5)
                        ->get(['id', 'method', 'hostname', 'uri', 'status', 'duration_ms', 'failed', 'created_at']),
                ],
            ],
        ]);
    }

    private function statusBuckets(Builder $query): array
    {
        return [
            '2xx' => (clone $query)->whereBetween('status', [200, 299])->count(),
            '3xx' => (clone $query)->whereBetween('status', [300, 399])->count(),
            '4xx' => (clone $query)->whereBetween('status', [400, 499])->count(),
            '5xx' => (clone $query)->where('status', '>=', 500)->count(),
        ];
    }
}
