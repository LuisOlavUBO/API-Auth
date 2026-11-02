<?php

use App\Http\ApiPrefix;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Middleware\RequirePkce;
use App\Http\Middleware\RequireRolesInClientApplication;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AuthorizationController;

// This is an API: the root leads to the docs. They live under the API prefix so that
// ubo-nginx-proxy routes them to this container; these shortcuts are for direct access.
Route::redirect('/', '/'.ApiPrefix::BASE.'/docs/swagger');
Route::redirect('docs/swagger', '/'.ApiPrefix::BASE.'/docs/swagger');

// The central login: the pages for people (ApiPrefix::WEB_PAGES), with session and CSRF.
Route::prefix(ApiPrefix::BASE)->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:auth');

    Route::get('logout', [LogoutController::class, 'show'])->name('logout');
    Route::post('logout', [LogoutController::class, 'store']);

    // Passport's authorization endpoint, the only one of its web routes in use (see AppServiceProvider).
    Route::get('oauth/authorize', [AuthorizationController::class, 'authorize'])
        ->middleware([RequirePkce::class, RequireRolesInClientApplication::class])
        ->name('passport.authorizations.authorize');
});
