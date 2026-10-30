<?php

namespace Tests;

use App\Models\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\Client;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create an OAuth client of an application (created when missing).
     *
     * @param  'client-credentials'|'password'  $type
     */
    protected function createClient(string $name, string $type = 'client-credentials', string $application = 'api-auth'): Client
    {
        return Application::firstOrCreate(['name' => $application])->createClient($name, $type);
    }
}
