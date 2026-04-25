<?php

use HttpBeacon\Http\Controllers\IncomingRequestController;
use HttpBeacon\Http\Controllers\OutgoingRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('beacon')->group(function () {
    Route::prefix('api')->group(function () {
        Route::get('incoming-requests', [IncomingRequestController::class, 'index']);
        Route::get('incoming-requests/{id}', [IncomingRequestController::class, 'show']);
        Route::get('outgoing-requests', [OutgoingRequestController::class, 'index']);
        Route::get('outgoing-requests/{id}', [OutgoingRequestController::class, 'show']);
    });

    Route::get('{any?}', fn () => view('beacon::layout'))->where('any', '.*');
});
