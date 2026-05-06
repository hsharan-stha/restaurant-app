<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\MenuApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\PaymentApiController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthTokenController::class, 'store']);

Route::get('/menu', [MenuApiController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderApiController::class, 'index']);
    Route::post('/orders', [OrderApiController::class, 'store']);
    Route::get('/orders/{order}', [OrderApiController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderApiController::class, 'updateStatus']);
    Route::post('/orders/{order}/payments', [PaymentApiController::class, 'store']);
});
