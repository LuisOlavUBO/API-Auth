<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Psr7\ServerRequest;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;

/**
 * Validates and revokes tokens through Passport's OAuth2 resource server, in-process.
 */
class OAuthTokenService
{
    public function __construct(protected ResourceServer $resourceServer) {}

    /**
     * Validate an access token (signature, expiry and revocation) and
     * return its claims, or null when the token is not active.
     *
     * Client credentials tokens carry the client ID as their "sub" claim,
     * so a subject equal to the client (UUIDs compare case-insensitively)
     * is reported as having no user.
     *
     * "audiences" is the token's "aud" claim: the client ID and, for tokens
     * issued for one API, that API's name. "roles" is the token's "roles" claim
     * and "user_uuid" its "uuid" claim (null in client tokens).
     *
     * @return array{token_id: string, client_id: string, user_id: string|null, user_uuid: string|null, scopes: string[], audiences: string[], roles: string[]}|null
     */
    public function introspect(string $accessToken): ?array
    {
        $request = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Bearer '.$accessToken);

        try {
            $validated = $this->resourceServer->validateAuthenticatedRequest($request);
        } catch (OAuthServerException) {
            return null;
        }

        $clientId = $validated->getAttribute('oauth_client_id');
        $subject = $validated->getAttribute('oauth_user_id');

        return [
            'token_id' => $validated->getAttribute('oauth_access_token_id'),
            'client_id' => $clientId,
            'user_id' => $subject && strcasecmp($subject, $clientId) !== 0 ? $subject : null,
            'user_uuid' => $this->userUuidOf($accessToken),
            'scopes' => $validated->getAttribute('oauth_scopes', []),
            'audiences' => $this->audiencesOf($accessToken),
            'roles' => $this->rolesOf($accessToken),
        ];
    }

    /**
     * The "uuid" claim of a token that the resource server already validated.
     */
    protected function userUuidOf(string $accessToken): ?string
    {
        $token = (new Parser(new JoseEncoder))->parse($accessToken);

        return $token instanceof UnencryptedToken
            ? $token->claims()->get('uuid')
            : null;
    }

    /**
     * The "aud" claim of a token that the resource server already validated.
     *
     * @return string[]
     */
    protected function audiencesOf(string $accessToken): array
    {
        $token = (new Parser(new JoseEncoder))->parse($accessToken);

        return $token instanceof UnencryptedToken
            ? array_values($token->claims()->get(RegisteredClaims::AUDIENCE, []))
            : [];
    }

    /**
     * The "roles" claim of a token that the resource server already validated.
     *
     * @return string[]
     */
    protected function rolesOf(string $accessToken): array
    {
        $token = (new Parser(new JoseEncoder))->parse($accessToken);

        return $token instanceof UnencryptedToken
            ? array_values($token->claims()->get('roles', []))
            : [];
    }

    /**
     * Revoke an access token and the refresh token bound to it.
     */
    public function revoke(string $tokenId): void
    {
        Passport::token()->newQuery()->whereKey($tokenId)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->where('access_token_id', $tokenId)->update(['revoked' => true]);
    }

    /**
     * Revoke every access token the user holds and their refresh tokens (all devices).
     */
    public function revokeAllFor(User $user): void
    {
        $user->tokens()->where('revoked', false)->pluck('id')->each(fn (string $id) => $this->revoke($id));
    }
}
