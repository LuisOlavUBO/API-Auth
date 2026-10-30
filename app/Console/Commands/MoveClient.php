<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesOAuthClient;
use App\Models\Application;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:client-application {client : Client id or name} {application : Application name}')]
#[Description('Move an OAuth client to another application')]
class MoveClient extends Command
{
    use ResolvesOAuthClient;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $client = $this->resolveClient($this->argument('client'));

        if (! $client) {
            return self::FAILURE;
        }

        $application = Application::firstWhere('name', $this->argument('application'));

        if (! $application) {
            $this->error("Unknown application [{$this->argument('application')}]. Create it with oauth:application.");

            return self::FAILURE;
        }

        $client->forceFill(['application_id' => $application->id])->save();

        $this->info("Client [{$client->name}] now belongs to [{$application->name}].");

        return self::SUCCESS;
    }
}
