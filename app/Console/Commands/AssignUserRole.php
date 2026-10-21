<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:assign-role {email : User email} {role : Role name}')]
#[Description('Assign a role (and therefore its scopes) to a user')]
class AssignUserRole extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::firstWhere('email', $this->argument('email'));
        $role = Role::firstWhere('name', $this->argument('role'));

        if (! $user || ! $role) {
            $this->error($user ? 'Role not found.' : 'User not found.');

            return self::FAILURE;
        }

        $user->role()->associate($role)->save();

        $this->info("User [{$user->email}] now has role [{$role->name}]. Applies to new logins and refreshes.");

        return self::SUCCESS;
    }
}
