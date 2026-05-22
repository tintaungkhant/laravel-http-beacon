<?php

use HttpBeacon\Http\Controllers\AssetController;
use HttpBeacon\Http\Controllers\DashboardController;
use HttpBeacon\Http\Controllers\IncomingRequestController;
use HttpBeacon\Http\Controllers\OutgoingRequestController;
use HttpBeacon\Http\Controllers\RecordingController;
use HttpBeacon\Http\Controllers\ShareController;
use HttpBeacon\Http\Controllers\SharedRequestController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

$sharingEnabled = (bool) config('beacon.sharing.enabled', true);

/*
 * Static assets — ungated. Compiled JS/CSS only, no captured data. Recipients
 * of shared links must be able to load these without Beacon access.
 */
Route::prefix('beacon')->group(function () {
    Route::get('assets/{path}', [AssetController::class, 'serve'])
        ->where('path', '[A-Za-z0-9._/-]+\.(css|js|map)')
        ->name('beacon.asset');
});

/*
 * Recipient-facing shared routes — ungated, 'web' middleware only (a session
 * is needed to remember the password unlock). Registered before the gated
 * group so they win over its catch-all.
 */
if ($sharingEnabled) {
    Route::middleware(['web'])
        ->prefix('beacon')
        ->group(function () {
            Route::get('api/shared/{token}', [SharedRequestController::class, 'show']);
            Route::post('api/shared/{token}/unlock', [SharedRequestController::class, 'unlock'])
                ->middleware(ThrottleRequests::class.':10,1'); // brute-force guard on password unlock
            Route::get('shared/{token}', fn () => view('beacon::layout'));
        });
}

/*
 * Gated Beacon routes — dashboard view + management JSON API.
 */
Route::middleware((array) config('beacon.middleware', ['web']))
    ->prefix('beacon')
    ->group(function () use ($sharingEnabled) {
        Route::prefix('api')->group(function () use ($sharingEnabled) {
            Route::get('dashboard', [DashboardController::class, 'index']);

            Route::get('recording', [RecordingController::class, 'show']);
            Route::post('recording/pause', [RecordingController::class, 'pause']);
            Route::post('recording/resume', [RecordingController::class, 'resume']);

            Route::get('incoming-requests', [IncomingRequestController::class, 'index']);
            Route::get('incoming-requests/{id}', [IncomingRequestController::class, 'show']);
            Route::delete('incoming-requests', [IncomingRequestController::class, 'destroy']);

            Route::get('outgoing-requests', [OutgoingRequestController::class, 'index']);
            Route::get('outgoing-requests/{id}', [OutgoingRequestController::class, 'show']);
            Route::delete('outgoing-requests', [OutgoingRequestController::class, 'destroy']);

            if ($sharingEnabled) {
                Route::get('shares', [ShareController::class, 'index']);
                Route::post('shares', [ShareController::class, 'store']);
                Route::delete('shares/{id}', [ShareController::class, 'destroy']);
            }
        });

        Route::get('{any?}', fn () => view('beacon::layout'))->where('any', '.*');
    });
