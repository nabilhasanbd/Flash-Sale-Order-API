<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Customer\ProductController as CustomerProductController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

Route::prefix('products')->group(function () {
    Route::get('/', [CustomerProductController::class, 'index']);
    Route::get('/{product}', [CustomerProductController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/wallet', [WalletController::class, 'index']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

    Route::prefix('admin')->group(function () {
        Route::apiResource('products', ProductController::class);
    });
});
