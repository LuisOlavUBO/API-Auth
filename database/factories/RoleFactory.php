<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
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
            'name' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Grant the role the given scopes, creating them if they do not exist.
     *
     * @param  string[]  $scopes
     */
    public function withScopes(array $scopes): static
    {
        return $this->afterCreating(function (Role $role) use ($scopes) {
            $role->oauthScopes()->sync(collect($scopes)->map(
                fn (string $name) => (OAuthScope::firstWhere('name', $name) ?? OAuthScope::factory()->create(['name' => $name]))->id
            ));
        });
    }
}
