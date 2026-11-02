<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:role {name : Role name, e.g. maestros-lector} {description? : What the role is for} {--application= : Application that owns the role (required)}')]
#[Description('Create or update a role of an application that can be granted to users and clients')]
class CreateRole extends Command
{
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

        $role = Role::updateOrCreate(
            ['application_id' => $application->id, 'name' => $this->argument('name')],
            array_filter(['description' => $this->argument('description')]),
        );

        $this->info("Role [{$application->name}/{$role->name}] saved.");

        return self::SUCCESS;
    }
}
