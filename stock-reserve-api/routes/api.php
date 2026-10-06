<?php

use App\Http\Controllers\InventoryController as C;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [C::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [C::class, 'login'])->middleware('throttle:5,1');
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::post('/logout', [C::class, 'logout']);
        Route::get('/products', [C::class, 'products']);
        Route::post('/products', [C::class, 'createProduct']);
        Route::get('/reservations', [C::class, 'reservations']);
        Route::post('/reservations', [C::class, 'reserve']);
        Route::post('/reservations/{id}/confirm', [C::class, 'confirm']);
        Route::post('/reservations/{id}/cancel', [C::class, 'cancel']);
        Route::get('/audit', [C::class, 'audit']);
    });
});
