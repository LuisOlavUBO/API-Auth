<?php

namespace App\Services;

use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use Laravel\Passport\ClientRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * Reads the scope catalog from the database and limits every token to the
 * scopes granted by roles: the user's roles in the application of the client
 * for user tokens, the client's roles for client credentials tokens.
 *
 * It also decides the applications named in the token's "aud" and the roles in
 * its "roles" claim, and leaves them in TokenAudience.
 */
class ScopeRepository extends PassportScopeRepository
{
    public function __construct(ClientRepository $clients, protected TokenAudience $audience)
    {
        parent::__construct($clients);
    }

    /**
     * Only scopes registered in oauth_scopes exist ("*" is not one of them).
     */
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return OAuthScope::where('name', $identifier)->exists() ? new Scope($identifier) : null;
    }

    /**
     * User tokens carry the scopes of the roles the user has in the application of the
     * client. Without requested scopes (Passport has no default scopes, so "none requested"
     * is an empty list, also when a code is exchanged) they carry all of them; asking for a
     * scope outside the roles is rejected. On refresh, scopes the roles no longer have are
     * dropped instead of failing it, and none are added.
     *
     * Client credentials tokens carry the scopes of the client's roles that were requested,
     * none when nothing is. A request with an "api" parameter gets a token for that
     * application only (see scopesForApplication). league/oauth2-server does not pass the
     * request here, so "api" is read from the current HTTP request: client credentials
     * tokens are only issued by POST /api/auth/oauth/token.
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
        $this->audience->issueFor();

        if ($userIdentifier !== null) {
            return $this->finalizeUserScopes($scopes, $grantType, $clientEntity, $userIdentifier);
        }

        $clientId = $clientEntity->getIdentifier();
        $applicationName = request()->input('api');

        if ($grantType === 'client_credentials' && is_string($applicationName) && $applicationName !== '') {
            $final = $this->scopesForApplication($scopes, $clientId, $applicationName);
            $this->audience->issueFor([$applicationName], $this->clientRolesGranting($clientId, $final));

            return $final;
        }

        $scopes = collect($scopes);
        $this->rejectScopesOutside($scopes, OAuthScope::namesForClient($clientId));
        $final = $scopes->values()->all();
        $this->audience->issueFor([], $this->clientRolesGranting($clientId, $final));

        return $final;
    }

    /**
     * @param  ScopeEntityInterface[]  $scopes
     * @return ScopeEntityInterface[]
     *
     * @throws OAuthServerException
     */
    protected function finalizeUserScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, string $userIdentifier): array
    {
        $user = User::find($userIdentifier);
        $applicationId = $this->applicationIdOf($clientEntity->getIdentifier());

        $allowed = $user && $applicationId ? $user->allowedScopeNames($applicationId) : [];
        $roles = $user && $applicationId ? $user->rolesIn($applicationId)->pluck('name')->all() : [];

        $scopes = collect($scopes);

        if ($grantType !== 'refresh_token' && $scopes->isEmpty()) {
            $final = array_map(fn (string $name): ScopeEntityInterface => new Scope($name), $allowed);
        } else {
            if ($grantType !== 'refresh_token') {
                $this->rejectScopesOutside($scopes, $allowed);
            }

            $final = $scopes
                ->filter(fn (ScopeEntityInterface $scope): bool => in_array($scope->getIdentifier(), $allowed, true))
                ->values()
                ->all();
        }

        $this->audience->issueFor($this->applicationsOwning($final), $roles);

        return $final;
    }

    /**
     * Token for one application: without "scope" it carries every scope the client's
     * roles grant in that application; with "scope" it carries the requested ones,
     * which must all be granted in that application. Unknown applications and
     * applications where the client has no scopes are rejected instead of issuing
     * an empty token.
     *
     * @param  ScopeEntityInterface[]  $scopes
     * @return ScopeEntityInterface[]
     *
     * @throws OAuthServerException
     */
    protected function scopesForApplication(array $scopes, string $clientId, string $applicationName): array
    {
        if (! Application::where('name', $applicationName)->exists()) {
            throw new OAuthServerException("Unknown API [{$applicationName}].", 3, 'invalid_request');
        }

        $allowed = OAuthScope::namesForClientInApplication($clientId, $applicationName);

        if (request()->filled('scope')) {
            foreach ($scopes as $scope) {
                if (! in_array($scope->getIdentifier(), $allowed, true)) {
                    throw OAuthServerException::invalidScope($scope->getIdentifier());
                }
            }

            return array_values($scopes);
        }

        if ($allowed === []) {
            throw new OAuthServerException("The client has no scopes in API [{$applicationName}].", 5, 'invalid_scope');
        }

        return array_map(fn (string $name): ScopeEntityInterface => new Scope($name), $allowed);
    }

    /**
     * Application the OAuth client belongs to.
     */
    protected function applicationIdOf(string $clientId): ?int
    {
        $id = DB::connection(config('passport.connection'))->table('oauth_clients')->where('id', $clientId)->value('application_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Names of the applications that own the given scopes, in alphabetical order.
     *
     * @param  ScopeEntityInterface[]  $scopes
     * @return string[]
     */
    protected function applicationsOwning(array $scopes): array
    {
        if ($scopes === []) {
            return [];
        }

        return OAuthScope::whereIn('name', array_map(fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), $scopes))
            ->with('application')
            ->get()
            ->pluck('application.name')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Names of the client's roles that grant at least one of the given scopes.
     *
     * @param  ScopeEntityInterface[]  $scopes
     * @return string[]
     */
    protected function clientRolesGranting(string $clientId, array $scopes): array
    {
        if ($scopes === []) {
            return [];
        }

        $names = array_map(fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), $scopes);

        return Role::whereHas('clients', fn (Builder $clients) => $clients->whereKey($clientId))
            ->whereHas('oauthScopes', fn (Builder $oauthScopes) => $oauthScopes->whereIn('name', $names))
            ->orderBy('name')
            ->pluck('name')
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
        foreach ($scopes as $scope) {
            $name = $scope->getIdentifier();

            if (! in_array($name, $allowed, true)) {
                throw OAuthServerException::invalidScope($name);
            }
        }
    }
}
