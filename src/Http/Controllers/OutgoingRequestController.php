<?php

namespace HttpBeacon\Http\Controllers;

use HttpBeacon\Models\OutgoingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OutgoingRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $beforeId = $request->query('before_id');

        $rows = OutgoingRequest::query()
            ->when($beforeId, fn ($q) => $q->where('id', '<', (int) $beforeId))
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
}
