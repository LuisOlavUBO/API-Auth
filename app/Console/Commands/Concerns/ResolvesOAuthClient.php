<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

trait ResolvesOAuthClient
{
    /**
     * Find a client by id or by its unique name, reporting errors to the console.
     */
    protected function resolveClient(string $idOrName): ?Client
    {
        $clients = Str::isUuid($idOrName)
            ? Passport::client()->newQuery()->whereKey($idOrName)->get()
            : Passport::client()->newQuery()->where('name', $idOrName)->get();

        if ($clients->isEmpty()) {
            $this->error("Client [{$idOrName}] not found.");

            return null;
        }

        if ($clients->count() > 1) {
            $this->error("Several clients are named [{$idOrName}]; use the client id instead.");

            return null;
        }

        return $clients->first();
    }
}
