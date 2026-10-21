<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passport\Passport;

class TokenIntrospectionController extends Controller
{
    public function __construct(protected OAuthTokenService $tokens) {}

    /**
     * RFC 7662 style introspection for other microservices. The caller
     * authenticates with a client credentials token carrying "introspect".
     */
    public function introspect(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $claims = $this->tokens->introspect($request->input('token'));

        if ($claims === null) {
            return response()->json(['active' => false]);
        }

        $token = Passport::token()->newQuery()->find($claims['token_id']);
        $user = $claims['user_id'] ? User::find($claims['user_id']) : null;

        return response()->json([
            'active' => true,
            'token_type' => 'Bearer',
            'client_id' => $claims['client_id'],
            'sub' => $claims['user_id'],
            'scope' => implode(' ', $claims['scopes']),
            'exp' => $token?->expires_at?->getTimestamp(),
            'user' => $user ? new UserResource($user) : null,
        ]);
    }

    /**
     * Public key so other services can verify JWT signatures locally.
     */
    public function publicKey(): Response
    {
        $key = config('passport.public_key')
            ?: file_get_contents(Passport::keyPath('oauth-public.key'));

        return response($key, 200, ['Content-Type' => 'application/x-pem-file']);
    }
}
