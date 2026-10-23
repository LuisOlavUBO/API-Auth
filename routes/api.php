<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TokenIntrospectionController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;
use UBO\Common\Health\Http\Controllers\HealthController;

Route::prefix('v1/auth')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->middleware(CheckToken::using('user:read'));
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });

    // Service-to-service endpoints (client credentials grant).
    Route::post('introspect', [TokenIntrospectionController::class, 'introspect'])
        ->middleware(EnsureClientIsResourceOwner::using('introspect'));

    Route::get('public-key', [TokenIntrospectionController::class, 'publicKey']);
});

// Health of the API and its database (nginx healthcheck).
Route::get('v1/salud', HealthController::class);
