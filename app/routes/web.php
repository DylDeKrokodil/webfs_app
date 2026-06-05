<?php

use App\Http\Controllers\MenuItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::view('/contact', 'app');
Route::view('/kassa', 'app');
Route::view('/admin/menu', 'app');

Route::get('/api/menu-items', [MenuItemController::class, 'index']);
