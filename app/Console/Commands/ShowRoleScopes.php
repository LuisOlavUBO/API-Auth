<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:role-scopes {application : Application name}')]
#[Description('Show the scopes each role of an application grants, grouped by the application that owns them')]
class ShowRoleScopes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $application = Application::firstWhere('name', $this->argument('application'));

        if (! $application) {
            $this->error("Unknown application [{$this->argument('application')}].");

            return self::FAILURE;
        }

        $rows = [];

        foreach ($application->roles()->with('oauthScopes.application')->orderBy('name')->get() as $role) {
            $byApplication = $role->oauthScopes->groupBy('application.name')->sortKeys();

            foreach ($byApplication as $owner => $scopes) {
                $rows[] = [$role->name, $owner, $scopes->pluck('name')->sort()->implode(', ')];
            }

            if ($byApplication->isEmpty()) {
                $rows[] = [$role->name, '-', '(no scopes)'];
            }
        }

        $this->table(['Role', 'Application', 'Scopes'], $rows);

        return self::SUCCESS;
    }
}
