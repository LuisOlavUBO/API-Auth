<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesRoles;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:assign-role {email : User email} {role : Role as <application>/<role>} {--sync : Replace the roles the user has in the application of the role}')]
#[Description('Assign a role (and therefore its scopes) to a user')]
class AssignUserRole extends Command
{
    use ResolvesRoles;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::firstWhere('email', $this->argument('email'));

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $role = $this->resolveRoles([$this->argument('role')])?->first();

        if (! $role) {
            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $user->roles()->detach($user->rolesIn($role->application_id)->pluck('id'));
        }

        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->info("User [{$user->email}] now has role [{$this->argument('role')}]. Applies to new logins and refreshes.");

        return self::SUCCESS;
    }
}
