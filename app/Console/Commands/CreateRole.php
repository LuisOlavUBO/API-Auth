<?php

namespace App\Console\Commands;

use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:role {name : Role name, e.g. maestros-lector} {description? : What the role is for}')]
#[Description('Create or update a role that can be granted to users and clients')]
class CreateRole extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $role = Role::updateOrCreate(
            ['name' => $this->argument('name')],
            array_filter(['description' => $this->argument('description')]),
        );

        $this->info("Role [{$role->name}] saved.");

        return self::SUCCESS;
    }
}
