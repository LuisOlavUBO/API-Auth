<?php

namespace App\Services;

use DateTimeImmutable;
use Laravel\Passport\Bridge\AccessToken as PassportAccessToken;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

/**
 * Passport's access token with the target API added to the "aud" claim.
 *
 * Passport sets "aud" to the client id only. When ScopeRepository issues a
 * token for one API (client credentials with "api"), the API name is added
 * after the client id: "aud": ["<client id>", "api-maestros"]. The client id
 * stays first so consumers that read it from aud[0] keep working, and each
 * API can reject tokens issued for another one.
 */
class AccessToken extends PassportAccessToken
{
    use AccessTokenTrait;

    /**
     * Request attribute where ScopeRepository leaves the validated API name.
     */
    public const API_ATTRIBUTE = 'oauth_api';

    protected ?string $api;

    /**
     * @param  non-empty-string|null  $userIdentifier
     * @param  ScopeEntityInterface[]  $scopes
     */
    public function __construct(?string $userIdentifier, array $scopes, ClientEntityInterface $client)
    {
        parent::__construct($userIdentifier, $scopes, $client);

        $api = request()->attributes->get(self::API_ATTRIBUTE);
        $this->api = is_string($api) && $api !== '' ? $api : null;
    }

    /**
     * Same claims as league/oauth2-server, with the API in the audience.
     */
    private function convertToJWT(): Token
    {
        $this->initJwtConfiguration();

        return $this->jwtConfiguration->builder()
            ->permittedFor(...array_filter([$this->getClient()->getIdentifier(), $this->api]))
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable)
            ->canOnlyBeUsedAfter(new DateTimeImmutable)
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getSubjectIdentifier())
            ->withClaim('scopes', $this->getScopes())
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }
}
