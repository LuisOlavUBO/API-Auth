<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesOAuthClient;
use App\Models\OAuthScope;
use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('oauth:client-role {client : Client id or name} {roles* : Role names} {--sync : Replace the client roles instead of adding to them}')]
#[Description('Grant roles (and therefore their scopes) to an OAuth client')]
class AssignClientRoles extends Command
{
    use ResolvesOAuthClient;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $client = $this->resolveClient($this->argument('client'));

        if (! $client) {
            return self::FAILURE;
        }

        $roleNames = $this->argument('roles');
        $roles = Role::whereIn('name', $roleNames)->get();
        $missing = array_diff($roleNames, $roles->pluck('name')->all());

        if ($missing !== []) {
            $this->error('Unknown role(s): '.implode(', ', $missing));

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
