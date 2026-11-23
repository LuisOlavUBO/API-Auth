<?php

namespace App\Services;

use App\Models\User;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

/**
 * The token response of league/oauth2-server plus the OpenID Connect "id_token", for user
 * tokens that carry "openid": both when a code is exchanged and on refresh. It is signed
 * with the same key as the access tokens and names that key in "kid".
 */
class IdTokenResponse extends BearerTokenResponse
{
    /**
     * @return array<string, string>
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        $nonce = app(TokenAudience::class)->pullNonce();
        $scopes = array_map(fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), $accessToken->getScopes());
        $userId = $accessToken->getUserIdentifier();
        $user = $userId !== null && in_array('openid', $scopes, true) ? User::find($userId) : null;

        if ($user === null) {
            return [];
        }

        return ['id_token' => $this->idToken($accessToken, $user, $scopes, $nonce)];
    }

    /**
     * @param  string[]  $scopes
     */
    protected function idToken(AccessTokenEntityInterface $accessToken, User $user, array $scopes, ?string $nonce): string
    {
        $oidc = app(OpenIdConnect::class);
        $claims = $oidc->userClaims($user, $scopes);

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($this->privateKey->getKeyContents(), $this->privateKey->getPassPhrase() ?? ''),
            InMemory::plainText($oidc->publicKey()),
        );

        $builder = $configuration->builder()
            ->withHeader('kid', $oidc->keyId())
            ->issuedBy($oidc->issuer())
            ->permittedFor(strtolower($accessToken->getClient()->getIdentifier()))
            ->relatedTo($claims['sub'])
            ->issuedAt(new DateTimeImmutable)
            ->expiresAt($accessToken->getExpiryDateTime());

        unset($claims['sub']);

        if ($nonce !== null) {
            $claims = ['nonce' => $nonce, ...$claims];
        }

        foreach ($claims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
    }
}
