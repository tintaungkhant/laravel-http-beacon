<?php

namespace HttpBeacon\Http\Controllers;

use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\OutgoingRequest;
use HttpBeacon\Models\SharedLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

/**
 * Recipient-facing API for a shared link — ungated. Protection is the token
 * itself plus the optional password / expiry / revoke flags on the link.
 *
 * The /beacon/shared/{token} page route serves the SPA shell directly from a
 * route closure (see routes/web.php); only the JSON endpoints live here.
 */
class SharedRequestController extends Controller
{
    public function show(Request $request, string $token): JsonResponse
    {
        $share = SharedLink::query()->where('token', $token)->first();

        if ($share === null) {
            return $this->state('missing');
        }

        if ($share->revoked_at !== null) {
            return $this->state('revoked');
        }

        if ($share->isExpired()) {
            return $this->state('expired');
        }

        if ($share->password !== null && ! $this->isUnlocked($request, $token)) {
            return $this->state('locked');
        }

        $entry = $this->resolveRequest($share);

        if ($entry === null) {
            return $this->state('missing');
        }

        // Atomic so concurrent views don't lose increments.
        $share->increment('view_count', 1, ['last_viewed_at' => now()]);

        return response()->json([
            'data' => [
                'status' => 'ok',
                'type' => $share->request_type,
                'request' => $entry,
            ],
        ]);
    }

    public function unlock(Request $request, string $token): JsonResponse
    {
        $share = SharedLink::query()->where('token', $token)->first();

        if ($share === null || ! $share->isValid() || $share->password === null) {
            return response()->json(['data' => ['unlocked' => false]], 404);
        }

        $password = $request->input('password');

        if (! is_string($password) || $password === '') {
            return response()->json(['data' => ['unlocked' => false]], 422);
        }

        if (! Hash::check($password, $share->password)) {
            return response()->json(['data' => ['unlocked' => false]], 422);
        }

        $request->session()->put($this->sessionKey($token), true);

        return response()->json(['data' => ['unlocked' => true]]);
    }

    private function state(string $status): JsonResponse
    {
        return response()->json(['data' => ['status' => $status]]);
    }

    private function isUnlocked(Request $request, string $token): bool
    {
        return (bool) $request->session()->get($this->sessionKey($token), false);
    }

    private function sessionKey(string $token): string
    {
        return 'beacon.shared.'.$token;
    }

    private function resolveRequest(SharedLink $share): ?Model
    {
        if ($share->request_type === 'incoming') {
            return IncomingRequest::query()
                ->with([
                    'queries:id,request_id,connection,type,sql,bindings,sql_with_bindings,time_ms,caller,created_at',
                    'modelTouches:id,request_id,model_class,model_id,action,changes,caller,created_at',
                    'jobDispatches:id,request_id,job_class,connection,queue,payload,caller,created_at',
                ])
                ->find($share->request_id);
        }

        return OutgoingRequest::query()->find($share->request_id);
    }
}
