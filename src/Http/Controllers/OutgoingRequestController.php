<?php

namespace HttpBeacon\Http\Controllers;

use Carbon\Carbon;
use HttpBeacon\Models\OutgoingRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OutgoingRequestController extends Controller
{
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    public function index(Request $request): JsonResponse
    {
        $query = OutgoingRequest::query();

        $this->applyFilters($query, $request);

        if ($beforeId = $request->query('before_id')) {
            $query->where('id', '<', (int) $beforeId);
        }

        $rows = $query
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id',
                'request_uuid',
                'hostname',
                'method',
                'uri',
                'status',
                'duration_ms',
                'failed',
                'created_at',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function show(string $id): JsonResponse
    {
        $request = OutgoingRequest::findOrFail($id);

        return response()->json(['data' => $request]);
    }

    public function destroy(): JsonResponse
    {
        OutgoingRequest::query()->delete();

        return response()->json(['data' => ['cleared' => true]]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('uri', 'like', '%'.$search.'%')
                  ->orWhere('hostname', 'like', '%'.$search.'%');
            });
        }

        $method = strtoupper((string) $request->query('method', ''));
        if ($method !== '' && in_array($method, self::ALLOWED_METHODS, true)) {
            $query->where('method', $method);
        }

        if ($request->boolean('failed')) {
            $query->where('failed', true);
        } elseif ($range = $this->statusRange((string) $request->query('status', ''))) {
            $query->whereBetween('status', $range);
        }

        if ($from = $this->parseDate($request->query('from'))) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $this->parseDate($request->query('to'))) {
            $query->where('created_at', '<=', $to);
        }
    }

    private function statusRange(string $status): ?array
    {
        return match (strtolower($status)) {
            '2xx' => [200, 299],
            '3xx' => [300, 399],
            '4xx' => [400, 499],
            '5xx' => [500, 599],
            default => null,
        };
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
}
