<?php

use App\Http\Controllers\Api\NodeApiController;
use App\Http\Controllers\Api\ServerApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['hypervm.api', 'throttle:120,1'])
    ->prefix('v1')
    ->group(function () {
        Route::get('/me', fn (\Illuminate\Http\Request $request) => [
            'data' => $request->user()->only(['uuid', 'name', 'username', 'email']),
        ]);

        Route::middleware('hypervm.api.permission:server.view.all')->group(function () {
            Route::get('/servers', [ServerApiController::class, 'index']);
            Route::get('/servers/{server}', [ServerApiController::class, 'show']);
            Route::get('/servers/{server}/status', [ServerApiController::class, 'status']);
        });

        Route::post('/servers/{server}/power', [ServerApiController::class, 'power'])
            ->middleware('hypervm.api.permission:server.power');

        Route::middleware('hypervm.api.permission:node.view')->group(function () {
            Route::get('/nodes', [NodeApiController::class, 'index']);
            Route::get('/nodes/{node}/status', [NodeApiController::class, 'status']);
        });
    });
