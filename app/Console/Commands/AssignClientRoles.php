<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesOAuthClient;
use App\Console\Commands\Concerns\ResolvesRoles;
use App\Models\OAuthScope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('oauth:client-role {client : Client id or name} {roles* : Roles as <application>/<role>} {--sync : Replace the client roles instead of adding to them}')]
#[Description('Grant roles (and therefore their scopes) to an OAuth client')]
class AssignClientRoles extends Command
{
    use ResolvesOAuthClient;
    use ResolvesRoles;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $client = $this->resolveClient($this->argument('client'));

        if (! $client) {
            return self::FAILURE;
        }

        $roles = $this->resolveRoles($this->argument('roles'));

        if (! $roles) {
            return self::FAILURE;
        }

        if ($this->option('sync')) {
            DB::table('oauth_client_role')->where('client_id', $client->getKey())->delete();
        }

        foreach ($roles as $role) {
            $role->clients()->syncWithoutDetaching([$client->getKey()]);
        }

        $this->info("Client [{$client->name}] scopes: ".(implode(', ', OAuthScope::namesForClient($client->getKey())) ?: '(none)'));

        return self::SUCCESS;
    }
}
