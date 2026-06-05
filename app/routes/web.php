<?php

use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\Admin\MenuItemController as AdminMenuItemController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\TabletOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::view('/contact', 'app');
Route::view('/menukaart', 'app');
Route::view('/tablet', 'app');
Route::view('/tablet/{tableNumber}', 'app')
    ->whereNumber('tableNumber');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::redirect('/admin', '/admin/menu');
    Route::view('/admin/kassa', 'app');
    Route::view('/admin/menu', 'app');

    Route::prefix('/api/admin')->group(function () {
        Route::apiResource('menu-items', AdminMenuItemController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::post('orders', [AdminOrderController::class, 'store']);
    });
});

Route::get('/api/menu-items', [MenuItemController::class, 'index']);
Route::get('/api/tablet/tables/{tableNumber}/status', [TabletOrderController::class, 'status'])
    ->whereNumber('tableNumber');
Route::post('/api/tablet/orders', [TabletOrderController::class, 'store']);
