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
 * token for one API (client credentials with "api"), AccessTokenRepository
 * passes the API name and it is added after the client id:
 * "aud": ["<client id>", "api-maestros"]. The client id stays first so
 * consumers that read it from aud[0] keep working, and each API can reject
 * tokens issued for another one.
 */
class AccessToken extends PassportAccessToken
{
    use AccessTokenTrait;

    /**
     * @param  non-empty-string|null  $userIdentifier
     * @param  ScopeEntityInterface[]  $scopes
     * @param  string|null  $api  API the token is for; null for a token for no particular API.
     */
    public function __construct(?string $userIdentifier, array $scopes, ClientEntityInterface $client, protected ?string $api = null)
    {
        parent::__construct($userIdentifier, $scopes, $client);
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
