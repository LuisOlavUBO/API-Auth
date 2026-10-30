<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:client {name : Client name, e.g. portal-backend} {--application= : Application the client belongs to (required)} {--type=client-credentials : client-credentials or password}')]
#[Description('Create an OAuth client of an application (passport:client cannot: it does not know the application)')]
class CreateClient extends Command
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

        if (! in_array($this->option('type'), ['client-credentials', 'password'], true)) {
            $this->error('The --type option must be client-credentials or password.');

            return self::FAILURE;
        }

        $client = $application->createClient($this->argument('name'), $this->option('type'));

        $this->info("Client [{$client->name}] created for [{$application->name}].");
        $this->line("Client id: {$client->getKey()}");
        $this->line("Client secret: {$client->plainSecret} (shown only now)");

        return self::SUCCESS;
    }
}
