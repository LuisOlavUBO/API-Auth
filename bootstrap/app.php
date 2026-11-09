<?php

use App\Http\ApiPrefix;
use App\Http\TrustedProxies;
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
        // Guests of the web pages go to the login; for the API, without "Accept: application/json"
        // the default tries route('login') and would redirect instead of answering 401.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is(...ApiPrefix::WEB_PAGES) ? route('login') : null);

        // Behind ubo-nginx-proxy the connection IP is the proxy's: the rate limits need the real one.
        $middleware->trustProxies(at: TrustedProxies::parse(env('TRUSTED_PROXIES')));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // api/* answers errors in RFC 9457 with the stable code in "error";
        // /api/auth/oauth/token keeps OAuth2's format.
        // The web pages (login, logout, authorize) answer HTML and redirections instead.
        $problems = new ProblemRenderer;
        $exceptions->render(fn (Throwable $e, Request $request) => $request->is(...ApiPrefix::WEB_PAGES) ? null : $problems($e, $request));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ($request->is('api/*') && ! $request->is(...ApiPrefix::WEB_PAGES)) || $request->expectsJson(),
        );
    })->create();
