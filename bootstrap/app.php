<?php

use App\Http\ApiPrefix;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use UBO\Common\Problems\ProblemRenderer;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: ApiPrefix::BASE,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // There is no web login to send guests to, and without "Accept: application/json"
        // the default tries route('login') and fails with a 500 instead of a 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // api/* answers errors in RFC 9457 with the stable code in "error" (OAuthTokenException included,
        // with its OAuth code); /api/auth/oauth/token keeps OAuth2's format.
        $exceptions->render(new ProblemRenderer);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
