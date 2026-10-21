<?php

namespace Tests\Feature;

use App\Models\Api;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthScopeCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);
    }

    public function test_role_command_creates_a_role(): void
    {
        $this->artisan('oauth:role', ['name' => 'maestros-lector', 'description' => 'Lectura de API-Maestros'])
            ->assertSuccessful();

        $this->assertDatabaseHas('roles', ['name' => 'maestros-lector', 'description' => 'Lectura de API-Maestros']);
    }

    public function test_api_command_creates_an_api(): void
    {
        $this->artisan('oauth:api', ['name' => 'api-maestros', 'description' => 'Datos maestros'])
            ->assertSuccessful();

        $this->assertDatabaseHas('apis', ['name' => 'api-maestros', 'description' => 'Datos maestros']);
    }

    public function test_scope_command_creates_a_scope_of_an_api_and_grants_it_to_roles(): void
    {
        $api = Api::factory()->create(['name' => 'api-maestros']);

        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía', '--api' => 'api-maestros', '--role' => ['admin']])
            ->assertSuccessful();

        $this->assertDatabaseHas('oauth_scopes', ['name' => 'geografia:leer', 'api_id' => $api->id]);
        $this->assertContains('geografia:leer', Role::firstWhere('name', 'admin')->oauthScopes()->pluck('name'));
        $this->assertNotContains('geografia:leer', Role::firstWhere('name', 'user')->oauthScopes()->pluck('name'));
    }

    public function test_scope_command_can_move_a_scope_to_another_api(): void
    {
        $api = Api::factory()->create(['name' => 'api-geo']);

        $this->artisan('oauth:scope', ['name' => 'user:read', 'description' => 'Read profile', '--api' => 'api-geo'])
            ->assertSuccessful();

        $this->assertSame($api->id, OAuthScope::firstWhere('name', 'user:read')->api_id);
    }

    public function test_scope_command_requires_a_known_api(): void
    {
        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía'])
            ->assertFailed();

        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía', '--api' => 'ghost'])
            ->assertFailed();

        $this->assertDatabaseMissing('oauth_scopes', ['name' => 'geografia:leer']);
    }

    public function test_scope_command_rejects_unknown_roles(): void
    {
        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía', '--api' => 'api-auth', '--role' => ['ghost']])
            ->assertFailed();

        $this->assertDatabaseMissing('oauth_scopes', ['name' => 'geografia:leer']);
    }

    public function test_assign_role_command_changes_the_user_role(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->artisan('oauth:assign-role', ['email' => 'ana@example.com', 'role' => 'admin'])
            ->assertSuccessful();

        $this->assertSame('admin', $user->fresh()->role->name);
    }
}
