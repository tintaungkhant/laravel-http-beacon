<?php

use HttpBeacon\Http\Controllers\DashboardController;
use HttpBeacon\Http\Controllers\IncomingRequestController;
use HttpBeacon\Http\Controllers\OutgoingRequestController;
use HttpBeacon\Http\Controllers\RecordingController;
use Illuminate\Support\Facades\Route;

Route::middleware((array) config('beacon.middleware', ['web']))
    ->prefix('beacon')
    ->group(function () {
        Route::prefix('api')->group(function () {
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
        });

        Route::get('{any?}', fn () => view('beacon::layout'))->where('any', '.*');
    });
