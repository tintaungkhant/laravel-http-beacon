<?php

namespace HttpBeacon\Http\Controllers;

use HttpBeacon\Models\IncomingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IncomingRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $beforeId = $request->query('before_id');

        $rows = IncomingRequest::query()
            ->when($beforeId, fn ($q) => $q->where('id', '<', (int) $beforeId))
            ->orderByDesc('id')
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
                'queries:id,request_id,connection,type,sql,bindings,sql_with_bindings,time_ms,created_at',
                'modelTouches:id,request_id,model_class,model_id,action,changes,created_at',
                'jobDispatches:id,request_id,job_class,connection,queue,payload,created_at',
            ])
            ->findOrFail($id);

        return response()->json(['data' => $request]);
    }
}
