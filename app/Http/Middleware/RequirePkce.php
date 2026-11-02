<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorization requests must carry a PKCE S256 challenge. It runs before the login so that
 * nobody types a password for a request that will be rejected anyway.
 */
class RequirePkce
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->filled('code_challenge') || $request->query('code_challenge_method') !== 'S256') {
            return response()->view('auth.message', [
                'title' => 'Solicitud no válida',
                'message' => 'La solicitud debe incluir code_challenge con code_challenge_method=S256.',
            ], 400);
        }

        return $next($request);
    }
}
