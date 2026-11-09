<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:client {name : Client name, e.g. portal-backend} {--application= : Application the client belongs to (required)} {--type=client-credentials : client-credentials or authorization-code} {--redirect=* : Redirect URL of an authorization-code client (repeatable)}')]
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

        if (! in_array($this->option('type'), ['client-credentials', 'authorization-code'], true)) {
            $this->error('The --type option must be client-credentials or authorization-code.');

            return self::FAILURE;
        }

        $redirects = $this->option('redirect');

        if ($this->option('type') === 'authorization-code' && (! $redirects || ! $this->areAbsoluteUrls($redirects))) {
            $this->error('An authorization-code client needs at least one --redirect, and each one must be an absolute URL.');

            return self::FAILURE;
        }

        $client = $application->createClient($this->argument('name'), $this->option('type'), $redirects);

        $this->info("Client [{$client->name}] created for [{$application->name}].");
        $this->line("Client id: {$client->getKey()}");
        $this->line("Client secret: {$client->plainSecret} (shown only now)");

        return self::SUCCESS;
    }

    /**
     * @param  string[]  $urls
     */
    protected function areAbsoluteUrls(array $urls): bool
    {
        return collect($urls)->every(fn (string $url) => preg_match('#^https?://[^\s/]+#i', $url) === 1);
    }
}
