<?php

namespace App\Services;

use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Passport\Bridge\ClientRepository as PassportClientRepository;
use Laravel\Passport\ClientRepository as ClientModelRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * Passport's repository, recording the clients that oauth/token rejects with invalid_client
 * (change eventos-de-autenticacion, D3).
 */
class ClientRepository extends PassportClientRepository
{
    public function __construct(ClientModelRepository $clients, Hasher $hasher, protected AuthenticationEvents $events)
    {
        parent::__construct($clients, $hasher);
    }

    /**
     * A missing or revoked client: invalid_client before its secret is checked.
     */
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $client = parent::getClientEntity($clientIdentifier);

        // The authorization endpoint also looks clients up; only oauth/token is recorded.
        if ($client === null && request()->routeIs('passport.token')) {
            $reason = $this->clients->find($clientIdentifier) === null ? 'cliente_inexistente' : 'cliente_revocado';
            $this->events->clientRejected($clientIdentifier, $reason);
        }

        return $client;
    }

    /**
     * An active client with a wrong secret.
     */
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $valid = parent::validateClient($clientIdentifier, $clientSecret, $grantType);

        if (! $valid) {
            $this->events->clientRejected($clientIdentifier, 'secreto_invalido');
        }

        return $valid;
    }
}
