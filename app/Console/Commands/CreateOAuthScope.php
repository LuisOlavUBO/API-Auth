<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesRoles;
use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:scope {name : Scope name, e.g. geografia:leer} {description : What the scope allows} {--application= : Application that owns the scope (required)} {--role=* : Roles (<application>/<role>) that may request it}')]
#[Description('Create or update an OAuth scope of an application and grant it to roles')]
class CreateOAuthScope extends Command
{
    use ResolvesRoles;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $application = Application::firstWhere('name', (string) $this->option('application'));

        if (! $application) {
            $this->error($this->option('application')
                ? "Unknown application [{$this->option('application')}]. Create it with oauth:application."
                : 'The --application option is required.');

            return self::FAILURE;
        }

        $roles = $this->resolveRoles($this->option('role'));

        if (! $roles) {
            return self::FAILURE;
        }

        $scope = OAuthScope::updateOrCreate(
            ['name' => $this->argument('name')],
            ['application_id' => $application->id, 'description' => $this->argument('description')],
        );

        $scope->roles()->syncWithoutDetaching($roles->pluck('id'));

        $this->info("Scope [{$scope->name}] of [{$application->name}] saved".($roles->isEmpty() ? '.' : ' for roles: '.$roles->map(fn (Role $role) => $role->application->name.'/'.$role->name)->implode(', ').'.'));

        return self::SUCCESS;
    }
}
