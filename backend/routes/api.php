<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// Authentication Routes (Public)
Route::prefix('auth')->group(function () {
    // Traditional email/password authentication
    Route::post('login', [AuthController::class, 'login']);
    Route::post('signup', [AuthController::class, 'signup']);
    Route::post('google-callback', [AuthController::class, 'googleCallback']);

    // Token refresh (public but requires valid refresh token)
    Route::post('refresh', [AuthController::class, 'refresh']);

    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Protected API Routes
Route::middleware('auth:sanctum')->group(function () {
    // User information
    Route::get('user', function (Request $request) {
        return $request->user();
    });

    // Product Management
    Route::apiResource('products', ProductController::class);
});
