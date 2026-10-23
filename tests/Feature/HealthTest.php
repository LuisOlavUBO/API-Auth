<?php

namespace Tests\Feature;

use App\Services\DatabaseHealth;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_queries_the_database(): void
    {
        $this->getJson('/api/v1/salud')
            ->assertOk()
            ->assertExactJson(['estado' => 'OK', 'base_datos' => 'OK']);
    }

    public function test_health_answers_503_when_the_database_is_down(): void
    {
        $this->mock(DatabaseHealth::class)->expects('isAvailable')->andReturnFalse();

        $this->getJson('/api/v1/salud')
            ->assertServiceUnavailable()
            ->assertExactJson(['estado' => 'ERROR', 'base_datos' => 'NO_DISPONIBLE']);
    }
}
