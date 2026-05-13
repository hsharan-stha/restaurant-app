<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\DiningSessionApiController;
use App\Http\Controllers\Api\MenuApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PrintingApiController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthTokenController::class, 'store']);

Route::get('/menu', [MenuApiController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/printing/printers', [PrintingApiController::class, 'printers']);
    Route::get('/printing/printers/{printer}/status', [PrintingApiController::class, 'printerStatus']);

    Route::get('/orders', [OrderApiController::class, 'index']);
    Route::post('/orders', [OrderApiController::class, 'store']);
    Route::patch('/orders/{order}/status', [OrderApiController::class, 'updateStatus']);
    Route::post('/orders/{order}/payments', [PaymentApiController::class, 'store']);

    Route::post('/dining-sessions', [DiningSessionApiController::class, 'createDiningSession']);
    Route::get('/dining-sessions/table/{tableId}/active', [DiningSessionApiController::class, 'getActiveDiningSessionByTable']);
    Route::post('/dining-sessions/{diningSession}/orders', [DiningSessionApiController::class, 'addOrderToSession']);
    Route::post('/dining-sessions/{diningSession}/checkout', [DiningSessionApiController::class, 'checkoutDiningSession']);
    Route::get('/dining-sessions/{diningSession}', [DiningSessionApiController::class, 'getDiningSessionDetails']);
    Route::get('/dining-sessions/completed/list', [DiningSessionApiController::class, 'getCompletedDiningSessions']);
});
