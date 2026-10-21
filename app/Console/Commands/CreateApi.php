<?php

namespace App\Console\Commands;

use App\Models\Api;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oauth:api {name : API name, e.g. api-maestros} {description? : What the API serves}')]
#[Description('Create or update an API (resource server) that owns scopes')]
class CreateApi extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $api = Api::updateOrCreate(
            ['name' => $this->argument('name')],
            array_filter(['description' => $this->argument('description')]),
        );

        $this->info("API [{$api->name}] saved.");

        return self::SUCCESS;
    }
}
