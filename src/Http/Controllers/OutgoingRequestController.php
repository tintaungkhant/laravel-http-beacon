<?php

namespace HttpBeacon\Http\Controllers;

use HttpBeacon\Models\OutgoingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class OutgoingRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = OutgoingRequest::query()
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
}
