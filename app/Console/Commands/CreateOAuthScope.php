<?php

namespace App\Console\Commands;

use App\Models\Api;
use App\Models\OAuthScope;
use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:scope {name : Scope name, e.g. geografia:leer} {description : What the scope allows} {--api= : API that owns the scope (required)} {--role=* : Roles that may request it}')]
#[Description('Create or update an OAuth scope of an API and grant it to roles')]
class CreateOAuthScope extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $api = Api::firstWhere('name', (string) $this->option('api'));

        if (! $api) {
            $this->error($this->option('api')
                ? "Unknown API [{$this->option('api')}]. Create it with oauth:api."
                : 'The --api option is required.');

            return self::FAILURE;
        }

        $roleNames = $this->option('role');
        $roles = Role::whereIn('name', $roleNames)->get();

        $missing = array_diff($roleNames, $roles->pluck('name')->all());

        if ($missing !== []) {
            $this->error('Unknown role(s): '.implode(', ', $missing));

            return self::FAILURE;
        }

        $scope = OAuthScope::updateOrCreate(
            ['name' => $this->argument('name')],
            ['api_id' => $api->id, 'description' => $this->argument('description')],
        );

        $scope->roles()->syncWithoutDetaching($roles->pluck('id'));

        $this->info("Scope [{$scope->name}] of [{$api->name}] saved".($roles->isEmpty() ? '.' : ' for roles: '.$roles->pluck('name')->implode(', ').'.'));

        return self::SUCCESS;
    }
}
