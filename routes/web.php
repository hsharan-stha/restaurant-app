<?php

use App\Http\Controllers\Admin\CatalogCategoryApiController;
use App\Http\Controllers\Admin\CatalogMenuItemApiController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerOrderingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\DiningTableFloorPlanController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemFulfillmentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PendingOrderItemController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\ThermalBillController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('guest/t/{table:qr_token}', [CustomerOrderingController::class, 'enter'])->name('guest.entry');
Route::get('table-order/{table}', [CustomerOrderingController::class, 'enter'])->name('table.order');
Route::get('guest/welcome', [CustomerOrderingController::class, 'welcome'])->name('guest.welcome');
Route::get('guest/scan-required', [CustomerOrderingController::class, 'needQr'])->name('guest.need-qr');
Route::post('guest/session/start', [CustomerOrderingController::class, 'startSession'])->name('guest.session.start');
Route::get('guest/menu', [CustomerOrderingController::class, 'menu'])->name('guest.menu');
Route::get('guest/order-summary', [CustomerOrderingController::class, 'orderSummary'])->name('guest.order-summary');
Route::post('guest/orders', [CustomerOrderingController::class, 'store'])->name('guest.orders.store');
Route::post('guest/checkout', [CustomerOrderingController::class, 'proceedToCheckout'])->name('guest.checkout');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('dashboard/poll', [DashboardController::class, 'poll'])->name('dashboard.poll');
    Route::get('dashboard/floor/state', [DashboardController::class, 'floorState'])->name('dashboard.floor.state');
    Route::get('dashboard/floor/tables/{diningTable}/panel', [DashboardController::class, 'tablePanel'])->name('dashboard.floor.table.panel');
    Route::get('orders/menu/catalog', [DashboardController::class, 'staffMenuCatalog'])->name('orders.menu.catalog');

    Route::middleware('role:admin,staff')->group(function () {
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('orders/{order}/items', [PendingOrderItemController::class, 'store'])->name('orders.items.store');
        Route::post('orders/{order}/items/{orderItem}/increment', [PendingOrderItemController::class, 'increment'])->name('orders.items.increment');
        Route::post('orders/{order}/items/{orderItem}/decrement', [PendingOrderItemController::class, 'decrement'])->name('orders.items.decrement');
        Route::delete('orders/{order}/items/{orderItem}', [PendingOrderItemController::class, 'destroy'])->name('orders.items.destroy');
        Route::patch('orders/{order}/items/{orderItem}', [PendingOrderItemController::class, 'update'])->name('orders.items.update');
        Route::post('orders/{order}/items/{orderItem}/mark-preparing', [OrderItemFulfillmentController::class, 'markPreparing'])->name('orders.items.mark-preparing');
        Route::post('orders/{order}/items/{orderItem}/mark-ready', [OrderItemFulfillmentController::class, 'markReady'])->name('orders.items.mark-ready');
        Route::post('orders/{order}/items/{orderItem}/deliver', [OrderItemFulfillmentController::class, 'deliver'])->name('orders.items.deliver');
        Route::post('orders/{order}/deliver-all-ready', [OrderItemFulfillmentController::class, 'deliverAllReady'])->name('orders.deliver-all-ready');
        Route::get('orders/{order}/pay', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('orders/{order}/pay', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('orders/{order}/bill/thermal', [ThermalBillController::class, 'show'])->name('bills.thermal');
        Route::get('orders/{order}/bill/thermal/pdf', [ThermalBillController::class, 'pdf'])->name('bills.thermal.pdf');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('reporting/completed-orders', [ReportingController::class, 'completedOrders'])->name('reporting.completed-orders');
        Route::get('reporting/delivery-performance', [ReportingController::class, 'deliveryPerformance'])->name('reporting.delivery-performance');
        Route::get('reporting/monthly-item-sales-matrix', [ReportingController::class, 'monthlyItemSalesMatrix'])->name('reporting.monthly-item-sales-matrix');
        Route::get('reporting/monthly-item-sales-matrix/export/csv', [ReportingController::class, 'monthlyItemSalesMatrixCsv'])->name('reporting.monthly-item-sales-matrix.csv');
        Route::get('reporting/monthly-item-sales-matrix/export/pdf', [ReportingController::class, 'monthlyItemSalesMatrixPdf'])->name('reporting.monthly-item-sales-matrix.pdf');

        Route::get('dining-tables/floor/data', [DiningTableFloorPlanController::class, 'data'])->name('dining-tables.floor.data');
        Route::post('dining-tables/floor/sync', [DiningTableFloorPlanController::class, 'sync'])->name('dining-tables.floor.sync');
        Route::post('dining-tables/floor/tables', [DiningTableFloorPlanController::class, 'store'])->name('dining-tables.floor.tables.store');
        Route::get('dining-tables/floor/tables/{diningTable}', [DiningTableFloorPlanController::class, 'show'])->name('dining-tables.floor.tables.show');
        Route::patch('dining-tables/floor/tables/{diningTable}', [DiningTableFloorPlanController::class, 'update'])->name('dining-tables.floor.tables.update');

        Route::get('dining-tables', [DiningTableController::class, 'floorPlan'])->name('dining-tables.index');

        Route::prefix('admin/catalog')->name('admin.catalog.')->group(function (): void {
            Route::get('categories', [CatalogCategoryApiController::class, 'index'])->name('categories.index');
            Route::post('categories', [CatalogCategoryApiController::class, 'store'])->name('categories.store');
            Route::patch('categories/{category}', [CatalogCategoryApiController::class, 'update'])->name('categories.update');
            Route::post('categories/{category}/toggle-active', [CatalogCategoryApiController::class, 'toggleActive'])->name('categories.toggle-active');
            Route::delete('categories/{category}', [CatalogCategoryApiController::class, 'destroy'])->name('categories.destroy');

            Route::get('menu-items', [CatalogMenuItemApiController::class, 'index'])->name('menu-items.index');
            Route::post('menu-items', [CatalogMenuItemApiController::class, 'store'])->name('menu-items.store');
            Route::patch('menu-items/{menuItem}', [CatalogMenuItemApiController::class, 'update'])->name('menu-items.update');
            Route::patch('menu-items/{menuItem}/inline', [CatalogMenuItemApiController::class, 'inlineUpdate'])->name('menu-items.inline-update');
            Route::post('menu-items/bulk', [CatalogMenuItemApiController::class, 'bulk'])->name('menu-items.bulk');
            Route::post('menu-items/{menuItem}/duplicate', [CatalogMenuItemApiController::class, 'duplicate'])->name('menu-items.duplicate');
            Route::delete('menu-items/{menuItem}', [CatalogMenuItemApiController::class, 'destroy'])->name('menu-items.destroy');
        });

        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('menu-items', MenuItemController::class)->except(['show']);
        Route::resource('dining-tables', DiningTableController::class)->except(['show', 'index']);
    });

    Route::middleware('role:admin,staff')->group(function () {
        Route::delete('customer-sessions/{customerSession}', [DiningTableController::class, 'destroyCustomerSession'])->name('customer-sessions.destroy');
        Route::post('dining-tables/{diningTable}/clear-session', [DiningTableController::class, 'clearSession'])->name('dining-tables.clear-session');
    });
});
