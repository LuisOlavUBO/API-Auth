<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

/**
 * A person with a session who has no role in the application of the client is not sent back
 * to the satellite. A guest passes: Passport sends them to the login, and the request is
 * evaluated again when they return. An unknown client passes too: Passport rejects it.
 */
class RequireRolesInClientApplication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $clientId = (string) $request->query('client_id');
        $client = $user && Str::isUuid($clientId) ? Passport::client()->newQuery()->find($clientId) : null;

        // prompt=login closes the session in Passport's controller; whoever logs in is checked on return.
        $startsNewLogin = in_array('login', explode(' ', (string) $request->query('prompt')), true)
            && ! $request->session()->get('promptedForLogin', false);

        if ($client && ! $startsNewLogin && $user->rolesIn($client->application_id)->isEmpty()) {
            return response()->view('auth.message', [
                'title' => 'Sin acceso',
                'message' => 'No tienes acceso a este sistema, contacta a soporte.',
            ], 403);
        }

        return $next($request);
    }
}
