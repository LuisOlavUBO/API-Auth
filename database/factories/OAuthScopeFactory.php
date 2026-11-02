<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\OAuthScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OAuthScope>
 */
class OAuthScopeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'name' => fake()->unique()->word().':'.fake()->randomElement(['leer', 'escribir']),
            'description' => fake()->sentence(),
        ];
    }
}
