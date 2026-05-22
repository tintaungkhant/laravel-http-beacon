<?php

namespace HttpBeacon\Http\Controllers;

use Carbon\Carbon;
use HttpBeacon\Models\IncomingRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IncomingRequestController extends Controller
{
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    public function index(Request $request): JsonResponse
    {
        $query = IncomingRequest::query();

        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $rows = $query
            ->limit(50)
            ->get([
                'id',
                'request_uuid',
                'method',
                'path',
                'status',
                'duration_ms',
                'memory_mb',
                'ip',
                'created_at',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function show(string $id): JsonResponse
    {
        $request = IncomingRequest::query()
            ->with([
                'queries:id,request_id,connection,type,sql,bindings,sql_with_bindings,time_ms,caller,created_at',
                'modelTouches:id,request_id,model_class,model_id,action,changes,caller,created_at',
                'jobDispatches:id,request_id,job_class,connection,queue,payload,caller,created_at',
            ])
            ->findOrFail($id);

        return response()->json(['data' => $request]);
    }

    public function destroy(): JsonResponse
    {
        IncomingRequest::query()->delete();

        return response()->json(['data' => ['cleared' => true]]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('path', 'like', $this->likePattern($search));
        }

        $method = strtoupper((string) $request->query('method', ''));
        if ($method !== '' && in_array($method, self::ALLOWED_METHODS, true)) {
            $query->where('method', $method);
        }

        if ($range = $this->statusRange((string) $request->query('status', ''))) {
            $query->whereBetween('status', $range);
        }

        if ($from = $this->parseDate($request->query('from'))) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $this->parseDate($request->query('to'))) {
            $query->where('created_at', '<=', $to);
        }

        $this->applyDurationFilter($query, $request);
    }

    private function applyDurationFilter(Builder $query, Request $request): void
    {
        $duration = $request->query('duration');

        if ($duration === null || $duration === '' || ! is_numeric($duration)) {
            return;
        }

        $operator = $request->query('duration_op') === 'lte' ? '<=' : '>=';

        $query->where('duration_ms', $operator, (int) $duration);
    }

    /**
     * Build a LIKE pattern. The search is always a substring (contains) match;
     * a `*` inside the term is a user-facing wildcard mapped to SQL `%`, so a
     * search of "star slash user" matches any path containing "/user".
     */
    private function likePattern(string $search): string
    {
        return '%'.str_replace('*', '%', $search).'%';
    }

    /**
     * Sort + paginate the listing.
     *
     * Sorting by `id` keeps keyset pagination (`before_id` cursor). Sorting by
     * `duration_ms` has no monotonic id cursor, so it falls back to offset
     * pagination (`offset` param).
     */
    private function applySort(Builder $query, Request $request): void
    {
        [$column, $direction] = match ($request->query('sort')) {
            'id_asc' => ['id', 'asc'],
            'duration_desc' => ['duration_ms', 'desc'],
            'duration_asc' => ['duration_ms', 'asc'],
            default => ['id', 'desc'],
        };

        if ($column === 'id') {
            if ($beforeId = $request->query('before_id')) {
                $query->where('id', $direction === 'asc' ? '>' : '<', (int) $beforeId);
            }

            $query->orderBy('id', $direction);

            return;
        }

        $query->orderBy($column, $direction)->orderByDesc('id');

        if (($offset = (int) $request->query('offset', 0)) > 0) {
            $query->offset($offset);
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
