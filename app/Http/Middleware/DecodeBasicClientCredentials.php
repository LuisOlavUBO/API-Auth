<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With client_secret_basic, RFC 6749 §2.3.1 form-urlencodes the client id and secret before
 * the Base64 of Authorization: Basic (openid-client sends the hyphens of the id as %2D), and
 * league/oauth2-server does not decode them. This rewrites the header with them decoded.
 */
class DecodeBasicClientCredentials
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization');
        $credentials = str_starts_with($header, 'Basic ') ? base64_decode(substr($header, 6), true) : false;

        if ($credentials !== false && str_contains($credentials, ':')) {
            [$clientId, $clientSecret] = array_map(urldecode(...), explode(':', $credentials, 2));

            $request->headers->set('Authorization', 'Basic '.base64_encode($clientId.':'.$clientSecret));
            $request->headers->set('PHP_AUTH_USER', $clientId);
            $request->headers->set('PHP_AUTH_PW', $clientSecret);
        }

        return $next($request);
    }
}
