<?php

namespace App\Services;

use DateTimeImmutable;
use Laravel\Passport\Bridge\AccessToken as PassportAccessToken;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

/**
 * Passport's access token with the applications added to the "aud" claim and
 * the roles in a "roles" claim.
 *
 * Passport sets "aud" to the client id only. ScopeRepository decides which
 * applications the token is for (the one named with "api" in a client
 * credentials token, the owners of its scopes in a user token) and
 * AccessTokenRepository passes them here, added after the client id:
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
     * @param  string[]  $applications  Applications the token is for, named in "aud" after the client id.
     * @param  string[]  $roles  Roles named in the "roles" claim.
     * @param  string|null  $userUuid  The user's public identifier, in a "uuid" claim; null in client tokens.
     */
    public function __construct(
        ?string $userIdentifier,
        array $scopes,
        ClientEntityInterface $client,
        protected array $applications = [],
        protected array $roles = [],
        protected ?string $userUuid = null,
    ) {
        parent::__construct($userIdentifier, $scopes, $client);
    }

    /**
     * Same claims as league/oauth2-server, with the applications in the audience,
     * the roles and, in user tokens, the user's uuid.
     */
    private function convertToJWT(): Token
    {
        $this->initJwtConfiguration();

        $builder = $this->jwtConfiguration->builder()
            ->permittedFor($this->getClient()->getIdentifier(), ...$this->applications)
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable)
            ->canOnlyBeUsedAfter(new DateTimeImmutable)
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getSubjectIdentifier())
            ->withClaim('scopes', $this->getScopes())
            ->withClaim('roles', $this->roles);

        if ($this->userUuid !== null) {
            $builder = $builder->withClaim('uuid', $this->userUuid);
        }

        return $builder->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }
}
