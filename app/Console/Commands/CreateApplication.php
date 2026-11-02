<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:application {name : Application name, e.g. api-maestros} {description? : What the application is for}')]
#[Description('Create or update an application (satellite, API or monolith) that owns scopes, roles and clients')]
class CreateApplication extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $application = Application::updateOrCreate(
            ['name' => $this->argument('name')],
            array_filter(['description' => $this->argument('description')]),
        );

        $this->info("Application [{$application->name}] saved.");

        return self::SUCCESS;
    }
}
