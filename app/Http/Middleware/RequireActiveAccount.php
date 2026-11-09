<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A person with a session gets no code while their account is disabled (the session is
 * closed) or while their password is a temporary one: they change it first, also when they
 * logged in with Microsoft, because the administrator knows it. A guest passes, as before.
 */
class RequireActiveAccount
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isDisabled()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->view('auth.message', [
                'title' => 'Sin acceso',
                'message' => 'No tienes acceso a este sistema, contacta a soporte.',
            ], 403);
        }

        if ($user?->hasTemporaryPassword()) {
            return redirect()->guest(route('password'));
        }

        return $next($request);
    }
}
