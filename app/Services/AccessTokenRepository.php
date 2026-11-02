<?php

namespace App\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Passport's repository, creating AccessToken with the applications and roles
 * that ScopeRepository set for it (see TokenAudience).
 */
class AccessTokenRepository extends PassportAccessTokenRepository
{
    public function __construct(Dispatcher $events, protected TokenAudience $audience)
    {
        parent::__construct($events);
    }

    /**
     * @param  ScopeEntityInterface[]  $scopes
     */
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        ?string $userIdentifier = null
    ): AccessTokenEntityInterface {
        [$applications, $roles] = $this->audience->pull();

        return new AccessToken($userIdentifier, $scopes, $clientEntity, $applications, $roles);
    }
}
