<?php

use App\Http\Controllers\Admin\AdminStatsController;
use App\Http\Controllers\Admin\MenuItemController as AdminMenuItemController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OrderLineOverviewController;
use App\Http\Controllers\Admin\SalesSummaryController;
use App\Http\Controllers\Admin\TableReceiptController;
use App\Http\Controllers\Admin\TableAssistanceRequestController as AdminTableAssistanceRequestController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\MenuPdfController;
use App\Http\Controllers\OrderLineNoteSuggestionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TableAssistanceRequestController;
use App\Http\Controllers\TabletOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::view('/contact', 'app');
Route::view('/menukaart', 'app');
Route::get('/menukaart.pdf', MenuPdfController::class)->name('public.menu.pdf');
Route::view('/tablet', 'app');
Route::view('/tablet/{tableNumber}', 'app')
    ->whereNumber('tableNumber');
Route::view('/review/{token}', 'app')
    ->where('token', '[A-Za-z0-9]+')
    ->name('reviews.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::redirect('/admin', '/admin/menu');
    Route::view('/admin/tafels', 'app');
    Route::view('/admin/kassa', 'app');
    Route::view('/admin/menu', 'app');
    Route::view('/admin/overzicht', 'app');
    Route::view('/admin/statistieken', 'app');

    Route::prefix('/api/admin')->group(function () {
        Route::apiResource('menu-items', AdminMenuItemController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::post('orders', [AdminOrderController::class, 'store']);
        Route::get('order-line-overview', [OrderLineOverviewController::class, 'index']);
        Route::get('stats', [AdminStatsController::class, 'index']);
        Route::get('sales-summaries', [SalesSummaryController::class, 'index']);
        Route::get('table-assistance-requests', [AdminTableAssistanceRequestController::class, 'index']);
        Route::post('table-assistance-requests/{tableAssistanceRequest}/resolve', [AdminTableAssistanceRequestController::class, 'resolve'])
            ->whereNumber('tableAssistanceRequest');
        Route::get('table-receipts', [TableReceiptController::class, 'index']);
        Route::post('table-receipts/{tableCode}/checkout', [TableReceiptController::class, 'checkout'])
            ->where('tableCode', '[A-Za-z0-9_-]+');
    });

    Route::get('/admin/sales-summaries/{generatedFile}/download', [SalesSummaryController::class, 'download'])
        ->whereNumber('generatedFile')
        ->name('admin.sales-summaries.download');

    Route::get('/admin/table-receipts/{tableCode}.pdf', [TableReceiptController::class, 'pdf'])
        ->where('tableCode', '[A-Za-z0-9_-]+')
        ->name('admin.table-receipts.pdf');
});

Route::get('/api/menu-items', [MenuItemController::class, 'index']);
Route::get('/api/order-line-note-suggestions', [OrderLineNoteSuggestionController::class, 'index']);
Route::get('/api/reviews/{token}', [ReviewController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+');
Route::post('/api/reviews/{token}', [ReviewController::class, 'store'])
    ->where('token', '[A-Za-z0-9]+');
Route::get('/api/tablet/tables/{tableNumber}/status', [TabletOrderController::class, 'status'])
    ->whereNumber('tableNumber');
Route::get('/api/tablet/tables/{tableNumber}/history', [TabletOrderController::class, 'history'])
    ->whereNumber('tableNumber');
Route::post('/api/tablet/tables/{tableNumber}/assistance-requests', [TableAssistanceRequestController::class, 'store'])
    ->whereNumber('tableNumber');
Route::post('/api/tablet/orders', [TabletOrderController::class, 'store']);

Route::fallback(function (Request $request) {
    if ($request->is('api/*')) {
        return response()->json([
            'message' => 'Niet gevonden.',
        ], 404);
    }

    return response()->view('app', [], 404);
});
