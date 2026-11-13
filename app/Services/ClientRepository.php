<?php

namespace App\Services;

use App\Models\Application;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository as PassportClientRepository;

/**
 * A client of a disabled application is not active, like a revoked one: Passport uses
 * findActive to issue and refresh tokens and in TokenGuard, and so does the introspection.
 */
class ClientRepository extends PassportClientRepository
{
    public function findActive(string|int $id): ?Client
    {
        $client = parent::findActive($id);

        // Queried every time: find() keeps the client for the rest of the request.
        $applicationIsActive = $client !== null && Application::whereKey($client->application_id)
            ->whereNull('disabled_at')
            ->exists();

        return $applicationIsActive ? $client : null;
    }
}
