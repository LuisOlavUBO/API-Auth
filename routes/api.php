<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TokenIntrospectionController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;
use UBO\Common\Health\Http\Controllers\HealthController;

// Every route here is under /api/auth (App\Http\ApiPrefix).

Route::prefix('v1')->group(function () {
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

    // Health of the API and its database (nginx healthcheck).
    Route::get('salud', HealthController::class);
});

// Passport's token endpoint, the only Passport route in use (AppServiceProvider ignores the
// others). Not versioned: it is OAuth2's standard endpoint, and it keeps OAuth2's error format
// because Passport renders its own exceptions before ProblemRenderer is asked.
Route::post('oauth/token', [AccessTokenController::class, 'issueToken'])
    ->middleware('throttle')
    ->name('passport.token');

// Docs: Swagger UI over docs/openapi/openapi.yaml, without a token.
Route::view('docs/swagger', 'docs.swagger');

Route::get('docs/openapi.yaml', function () {
    return response()->file(base_path('docs/openapi/openapi.yaml'), [
        'Content-Type' => 'application/yaml; charset=UTF-8',
    ]);
});
