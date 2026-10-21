<?php

namespace App\Services;

use App\Models\OAuthScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * Reads the scope catalog from the database and limits every token to the
 * scopes granted by roles: the user's role for user tokens, the client's
 * roles for client credentials tokens.
 */
class ScopeRepository extends PassportScopeRepository
{
    /**
     * Only scopes registered in oauth_scopes exist ("*" is not one of them).
     */
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return OAuthScope::where('name', $identifier)->exists() ? new Scope($identifier) : null;
    }

    /**
     * On login and client credentials requests, asking for a scope outside
     * the roles is rejected, while default scopes the roles lack are dropped
     * so a request without "scope" still works. On refresh, scopes the role
     * no longer has are dropped instead of failing the refresh. Clients
     * without roles get no scopes.
     *
     * @param  ScopeEntityInterface[]  $scopes
     * @return ScopeEntityInterface[]
     *
     * @throws OAuthServerException
     */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        $allowed = $userIdentifier === null
            ? OAuthScope::namesForClient($clientEntity->getIdentifier())
            : User::find($userIdentifier)?->allowedScopeNames() ?? [];

        $scopes = collect($scopes);

        if (in_array($grantType, ['password', 'client_credentials'], true)) {
            $this->rejectScopesOutside($scopes, $allowed);
        }

        return $scopes
            ->filter(fn (ScopeEntityInterface $scope): bool => in_array($scope->getIdentifier(), $allowed, true))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ScopeEntityInterface>  $scopes
     * @param  string[]  $allowed
     *
     * @throws OAuthServerException
     */
    protected function rejectScopesOutside(Collection $scopes, array $allowed): void
    {
        $defaults = Passport::defaultScopes();

        foreach ($scopes as $scope) {
            $name = $scope->getIdentifier();

            if (! in_array($name, $allowed, true) && ! in_array($name, $defaults, true)) {
                throw OAuthServerException::invalidScope($name);
            }
        }
    }
}
