<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerProductController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

Route::prefix('customer')->group(function () {
    Route::get('/products', [CustomerProductController::class, 'index']);
    Route::get('/products/{product}', [CustomerProductController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('products', ProductController::class);
});
