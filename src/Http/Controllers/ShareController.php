<?php

namespace HttpBeacon\Http\Controllers;

use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\OutgoingRequest;
use HttpBeacon\Models\SharedLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Management API for shared links — gated behind beacon.middleware.
 */
class ShareController extends Controller
{
    /** Expiry preset => hours from now. Anything else means "never". */
    private const EXPIRY_HOURS = [
        '1h' => 1,
        '24h' => 24,
        '7d' => 168,
        '30d' => 720,
    ];

    public function index(Request $request): JsonResponse
    {
        $query = SharedLink::query()->orderByDesc('id');

        $type = (string) $request->query('request_type', '');
        $id = $request->query('request_id');

        if (in_array($type, ['incoming', 'outgoing'], true) && is_numeric($id)) {
            $query->where('request_type', $type)->where('request_id', (int) $id);
        }

        return response()->json([
            'data' => $query->limit(100)->get()->map(fn (SharedLink $s) => $this->present($s))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_type' => ['required', 'in:incoming,outgoing'],
            'request_id' => ['required', 'integer'],
            'expiry' => ['nullable', 'in:1h,24h,7d,30d,never'],
            'password' => ['nullable', 'string'],
        ]);

        $type = $validated['request_type'];
        $id = (int) $validated['request_id'];
        $expiry = $validated['expiry'] ?? 'never';
        $password = $validated['password'] ?? null;

        $model = $type === 'incoming' ? IncomingRequest::class : OutgoingRequest::class;

        if (! $model::query()->whereKey($id)->exists()) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $share = SharedLink::create([
            'token' => Str::random(48),
            'request_type' => $type,
            'request_id' => $id,
            'password' => is_string($password) && $password !== ''
                ? Hash::make($password)
                : null,
            'expires_at' => isset(self::EXPIRY_HOURS[$expiry])
                ? now()->addHours(self::EXPIRY_HOURS[$expiry])
                : null,
            'revoked_at' => null,
            'view_count' => 0,
            'last_viewed_at' => null,
            'created_at' => now(),
        ]);

        return response()->json(['data' => $this->present($share)], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $share = SharedLink::query()->findOrFail($id);

        if ($share->revoked_at === null) {
            $share->revoked_at = now();
            $share->save();
        }

        return response()->json(['data' => $this->present($share)]);
    }

    /**
     * Shape a shared link for the API — never exposes the password hash.
     *
     * @return array<string, mixed>
     */
    private function present(SharedLink $share): array
    {
        return [
            'id' => $share->id,
            'token' => $share->token,
            'request_type' => $share->request_type,
            'request_id' => $share->request_id,
            'url' => url('beacon/shared/'.$share->token),
            'has_password' => $share->password !== null,
            'status' => $share->status(),
            'view_count' => $share->view_count,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'revoked_at' => $share->revoked_at?->toIso8601String(),
            'last_viewed_at' => $share->last_viewed_at?->toIso8601String(),
            'created_at' => $share->created_at->toIso8601String(),
        ];
    }
}
