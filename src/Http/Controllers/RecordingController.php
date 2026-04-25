<?php

namespace HttpBeacon\Http\Controllers;

use HttpBeacon\Beacon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RecordingController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->state();
    }

    public function pause(): JsonResponse
    {
        Beacon::pause();

        return $this->state();
    }

    public function resume(): JsonResponse
    {
        Beacon::resume();

        return $this->state();
    }

    private function state(): JsonResponse
    {
        return response()->json([
            'data' => ['recording' => Beacon::isRecording()],
        ]);
    }
}
