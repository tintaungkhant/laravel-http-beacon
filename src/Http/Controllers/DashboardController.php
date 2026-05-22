<?php

namespace HttpBeacon\Http\Controllers;

use Carbon\Carbon;
use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\OutgoingRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$since, $until] = $this->resolveWindow($request);

        $incoming = IncomingRequest::query()->where('created_at', '>=', $since);
        $outgoing = OutgoingRequest::query()->where('created_at', '>=', $since);

        if ($until) {
            $incoming->where('created_at', '<=', $until);
            $outgoing->where('created_at', '<=', $until);
        }

        return response()->json([
            'data' => [
                'since' => $since->toIso8601String(),
                'until' => $until?->toIso8601String(),
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
                    'status_buckets' => $this->statusBuckets(clone $outgoing),
                    'slowest' => (clone $outgoing)
                        ->orderByDesc('duration_ms')
                        ->limit(5)
                        ->get(['id', 'method', 'hostname', 'uri', 'status', 'duration_ms', 'failed', 'created_at']),
                ],
            ],
        ]);
    }

    /**
     * @return array{0:Carbon,1:?Carbon}
     */
    private function resolveWindow(Request $request): array
    {
        $since = $this->parseDate($request->query('from')) ?? now()->subDay();
        $until = $this->parseDate($request->query('to'));

        return [$since, $until];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
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
