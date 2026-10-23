<?php

namespace App\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Passport's repository, creating AccessToken with the API that ScopeRepository
 * validated for it (see TokenAudience).
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
        return new AccessToken($userIdentifier, $scopes, $clientEntity, $this->audience->pull());
    }
}
