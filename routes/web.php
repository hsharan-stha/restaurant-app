<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerOrderingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('guest/t/{table:qr_token}', [CustomerOrderingController::class, 'enter'])->name('guest.entry');
Route::get('guest/menu', [CustomerOrderingController::class, 'menu'])->name('guest.menu');
Route::post('guest/orders', [CustomerOrderingController::class, 'store'])->name('guest.orders.store');
Route::post('guest/checkout', [CustomerOrderingController::class, 'proceedToCheckout'])->name('guest.checkout');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('dashboard/poll', [DashboardController::class, 'poll'])->name('dashboard.poll');

    Route::middleware('role:admin,staff')->group(function () {
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::get('orders/{order}/invoice', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('orders/{order}/invoice/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('orders/{order}/pay', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('orders/{order}/pay', [PaymentController::class, 'store'])->name('payments.store');
    });

    Route::middleware('role:admin')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('menu-items', MenuItemController::class)->except(['show']);
        Route::resource('dining-tables', DiningTableController::class)->except(['show']);
    });
});
