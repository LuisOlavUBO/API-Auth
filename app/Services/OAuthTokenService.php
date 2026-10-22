<?php

namespace App\Services;

use App\Exceptions\OAuthTokenException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\ServerRequest;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;

/**
 * Issues and validates tokens by talking to Passport's OAuth2 server
 * in-process, so the public auth endpoints never expose client secrets.
 */
class OAuthTokenService
{
    public function __construct(
        protected AuthorizationServer $authorizationServer,
        protected ResourceServer $resourceServer,
    ) {}

    /**
     * Issue an access/refresh token pair using the password grant.
     *
     * @return array{token_type: string, expires_in: int, access_token: string, refresh_token: string}
     */
    public function issueForCredentials(string $email, string $password, ?string $scope = null): array
    {
        return $this->requestToken([
            'grant_type' => 'password',
            'username' => $email,
            'password' => $password,
            'scope' => $scope ?? '',
        ]);
    }

    /**
     * Exchange a refresh token for a new access/refresh token pair.
     *
     * @return array{token_type: string, expires_in: int, access_token: string, refresh_token: string}
     */
    public function refresh(string $refreshToken, ?string $scope = null): array
    {
        return $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => $scope ?? '',
        ]);
    }

    /**
     * Validate an access token (signature, expiry and revocation) and
     * return its claims, or null when the token is not active.
     *
     * Client credentials tokens carry the client ID as their "sub" claim,
     * so a subject equal to the client (UUIDs compare case-insensitively)
     * is reported as having no user.
     *
     * "audiences" is the token's "aud" claim: the client ID and, for tokens
     * issued for one API, that API's name.
     *
     * @return array{token_id: string, client_id: string, user_id: string|null, scopes: string[], audiences: string[]}|null
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
            'scopes' => $validated->getAttribute('oauth_scopes', []),
            'audiences' => $this->audiencesOf($accessToken),
        ];
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
     * Revoke an access token and the refresh token bound to it.
     */
    public function revoke(string $tokenId): void
    {
        Passport::token()->newQuery()->whereKey($tokenId)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->where('access_token_id', $tokenId)->update(['revoked' => true]);
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    protected function requestToken(array $params): array
    {
        $request = (new ServerRequest('POST', '/oauth/token'))->withParsedBody([
            ...$params,
            'client_id' => config('oauth.password_client.id'),
            'client_secret' => config('oauth.password_client.secret'),
        ]);

        try {
            $response = $this->authorizationServer->respondToAccessTokenRequest($request, new Psr7Response);
        } catch (OAuthServerException $e) {
            throw OAuthTokenException::fromServerException($e);
        }

        return json_decode((string) $response->getBody(), true);
    }
}
