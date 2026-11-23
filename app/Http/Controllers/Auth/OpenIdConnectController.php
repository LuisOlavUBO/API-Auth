<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OpenIdConnect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The endpoints of OpenID Connect: discovery, the signing keys and userinfo.
 */
class OpenIdConnectController extends Controller
{
    public function __construct(protected OpenIdConnect $oidc) {}

    /**
     * Discovery document. Every URL comes from the issuer, not from the request, so they
     * match the public URL behind the proxy.
     */
    public function configuration(): JsonResponse
    {
        $issuer = $this->oidc->issuer();

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'userinfo_endpoint' => $issuer.'/oauth/userinfo',
            'jwks_uri' => $issuer.'/.well-known/jwks.json',
            'end_session_endpoint' => $issuer.'/logout',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => OpenIdConnect::SCOPES,
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['iss', 'sub', 'aud', 'exp', 'iat', 'nonce', 'id_persona', 'name', 'email', 'email_verified'],
        ]);
    }

    /**
     * The public key that signs the tokens, as a JWK set.
     */
    public function jwks(): JsonResponse
    {
        return response()->json(['keys' => [$this->oidc->jwk()]]);
    }

    /**
     * The claims about the owner of an access token with "openid", for the scopes it carries.
     */
    public function userinfo(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $scopes = array_values(array_filter(OpenIdConnect::SCOPES, fn (string $scope): bool => $user->tokenCan($scope)));

        return response()->json($this->oidc->userClaims($user, $scopes));
    }
}
