<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OAuthScopeCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);
    }

    public function test_application_command_creates_and_updates_an_application(): void
    {
        $this->artisan('oauth:application', ['name' => 'api-maestros', 'description' => 'Datos maestros'])
            ->assertSuccessful();

        $this->assertDatabaseHas('applications', ['name' => 'api-maestros', 'description' => 'Datos maestros']);

        $this->artisan('oauth:application', ['name' => 'api-maestros', 'description' => 'Maestros'])
            ->assertSuccessful();

        $this->assertSame(1, Application::where('name', 'api-maestros')->count());
        $this->assertSame('Maestros', Application::firstWhere('name', 'api-maestros')->description);
    }

    public function test_role_command_creates_a_role_of_an_application(): void
    {
        $maestros = Application::factory()->create(['name' => 'api-maestros']);

        $this->artisan('oauth:role', ['name' => 'maestros-lector', 'description' => 'Lectura de API-Maestros', '--application' => 'api-maestros'])
            ->assertSuccessful();

        $this->assertDatabaseHas('roles', ['name' => 'maestros-lector', 'application_id' => $maestros->id, 'description' => 'Lectura de API-Maestros']);
    }

    public function test_role_command_allows_the_same_name_in_another_application(): void
    {
        Application::factory()->create(['name' => 'api-maestros']);
        Application::factory()->create(['name' => 'portal']);

        $this->artisan('oauth:role', ['name' => 'lector', '--application' => 'api-maestros'])->assertSuccessful();
        $this->artisan('oauth:role', ['name' => 'lector', '--application' => 'portal'])->assertSuccessful();

        $this->assertSame(2, Role::where('name', 'lector')->count());
    }

    public function test_role_command_requires_a_known_application(): void
    {
        $this->artisan('oauth:role', ['name' => 'lector'])->assertFailed();
        $this->artisan('oauth:role', ['name' => 'lector', '--application' => 'ghost'])->assertFailed();

        $this->assertDatabaseMissing('roles', ['name' => 'lector']);
    }

    public function test_scope_command_creates_a_scope_of_an_application_and_grants_it_to_roles(): void
    {
        $maestros = Application::factory()->create(['name' => 'api-maestros']);

        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía', '--application' => 'api-maestros', '--role' => ['api-auth/admin']])
            ->assertSuccessful();

        $this->assertDatabaseHas('oauth_scopes', ['name' => 'geografia:leer', 'application_id' => $maestros->id]);
        $this->assertContains('geografia:leer', Role::firstWhere('name', 'admin')->oauthScopes()->pluck('name'));
        $this->assertNotContains('geografia:leer', Role::firstWhere('name', 'user')->oauthScopes()->pluck('name'));
    }

    public function test_scope_command_can_grant_a_scope_to_a_role_of_another_application(): void
    {
        Application::factory()->create(['name' => 'api-maestros']);
        $portal = Application::factory()->create(['name' => 'portal']);
        Role::factory()->for($portal)->create(['name' => 'vendedor']);

        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía', '--application' => 'api-maestros', '--role' => ['portal/vendedor']])
            ->assertSuccessful();

        $this->assertContains('geografia:leer', Role::firstWhere('name', 'vendedor')->oauthScopes()->pluck('name'));
    }

    public function test_scope_command_can_move_a_scope_to_another_application(): void
    {
        $geo = Application::factory()->create(['name' => 'api-geo']);

        $this->artisan('oauth:scope', ['name' => 'user:read', 'description' => 'Read profile', '--application' => 'api-geo'])
            ->assertSuccessful();

        $this->assertSame($geo->id, OAuthScope::firstWhere('name', 'user:read')->application_id);
    }

    public function test_scope_command_requires_a_known_application(): void
    {
        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía'])
            ->assertFailed();

        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía', '--application' => 'ghost'])
            ->assertFailed();

        $this->assertDatabaseMissing('oauth_scopes', ['name' => 'geografia:leer']);
    }

    public function test_scope_command_rejects_unknown_roles(): void
    {
        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía', '--application' => 'api-auth', '--role' => ['api-auth/ghost']])
            ->assertFailed();

        $this->artisan('oauth:scope', ['name' => 'geografia:leer', 'description' => 'Leer geografía', '--application' => 'api-auth', '--role' => ['admin']])
            ->assertFailed();

        $this->assertDatabaseMissing('oauth_scopes', ['name' => 'geografia:leer']);
    }

    public function test_assign_role_command_adds_a_role_to_a_user(): void
    {
        $user = User::factory()->withRoles(Role::firstWhere('name', 'user'))->create(['email' => 'ana@example.com']);

        $this->artisan('oauth:assign-role', ['email' => 'ana@example.com', 'role' => 'api-auth/admin'])
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['admin', 'user'], $user->fresh()->roles->pluck('name')->all());
    }

    public function test_assign_role_command_sync_replaces_only_the_roles_of_that_application(): void
    {
        $portal = Application::factory()->create(['name' => 'portal']);
        $vendedor = Role::factory()->for($portal)->create(['name' => 'vendedor']);
        $user = User::factory()->withRoles(Role::firstWhere('name', 'user'), $vendedor)->create(['email' => 'ana@example.com']);

        $this->artisan('oauth:assign-role', ['email' => 'ana@example.com', 'role' => 'api-auth/admin', '--sync' => true])
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['admin', 'vendedor'], $user->fresh()->roles->pluck('name')->all());
    }

    public function test_assign_role_command_rejects_unknown_users_and_roles(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->artisan('oauth:assign-role', ['email' => 'nobody@example.com', 'role' => 'api-auth/admin'])->assertFailed();
        $this->artisan('oauth:assign-role', ['email' => 'ana@example.com', 'role' => 'api-auth/ghost'])->assertFailed();
        $this->artisan('oauth:assign-role', ['email' => 'ana@example.com', 'role' => 'admin'])->assertFailed();
    }

    public function test_client_command_creates_a_client_of_an_application(): void
    {
        $portal = Application::factory()->create(['name' => 'portal']);

        $this->artisan('oauth:client', ['name' => 'portal-backend', '--application' => 'portal', '--type' => 'client-credentials'])
            ->expectsOutputToContain('Client id:')
            ->expectsOutputToContain('Client secret:')
            ->assertSuccessful();

        $client = Passport::client()->newQuery()->where('name', 'portal-backend')->first();

        $this->assertSame($portal->id, (int) $client->application_id);
        $this->assertTrue($client->hasGrantType('client_credentials'));
    }

    public function test_client_command_requires_a_known_application_and_type(): void
    {
        Application::factory()->create(['name' => 'portal']);

        $this->artisan('oauth:client', ['name' => 'portal-backend'])->assertFailed();
        $this->artisan('oauth:client', ['name' => 'portal-backend', '--application' => 'ghost'])->assertFailed();
        $this->artisan('oauth:client', ['name' => 'portal-backend', '--application' => 'portal', '--type' => 'implicit'])->assertFailed();

        $this->assertSame(0, Passport::client()->newQuery()->where('name', 'portal-backend')->count());
    }

    public function test_client_command_creates_an_authorization_code_client(): void
    {
        $portal = Application::factory()->create(['name' => 'portal']);

        $this->artisan('oauth:client', [
            'name' => 'portal-web',
            '--application' => 'portal',
            '--type' => 'authorization-code',
            '--redirect' => ['https://portal.ubo.cl/callback', 'http://localhost:8080/callback'],
        ])->expectsOutputToContain('Client secret:')->assertSuccessful();

        $client = Passport::client()->newQuery()->where('name', 'portal-web')->first();

        $this->assertSame($portal->id, (int) $client->application_id);
        $this->assertTrue($client->confidential());
        $this->assertTrue($client->hasGrantType('authorization_code'));
        $this->assertTrue($client->hasGrantType('refresh_token'));
        $this->assertFalse($client->hasGrantType('client_credentials'));
        $this->assertSame(['https://portal.ubo.cl/callback', 'http://localhost:8080/callback'], $client->redirect_uris);
        $this->assertTrue($client->skipsAuthorization(User::factory()->make(), []));
    }

    public function test_client_command_rejects_an_authorization_code_client_without_a_valid_redirect(): void
    {
        Application::factory()->create(['name' => 'portal']);
        $options = ['name' => 'portal-web', '--application' => 'portal', '--type' => 'authorization-code'];

        $this->artisan('oauth:client', $options)->assertFailed();
        $this->artisan('oauth:client', [...$options, '--redirect' => ['/callback']])->assertFailed();
        $this->artisan('oauth:client', [...$options, '--redirect' => ['https://portal.ubo.cl/callback', 'callback']])->assertFailed();

        $this->assertSame(0, Passport::client()->newQuery()->where('name', 'portal-web')->count());
    }

    public function test_client_application_command_moves_a_client(): void
    {
        $maestros = Application::factory()->create(['name' => 'api-maestros']);
        $this->createClient('maestros-backend');

        $this->artisan('oauth:client-application', ['client' => 'maestros-backend', 'application' => 'api-maestros'])
            ->assertSuccessful();

        $this->artisan('oauth:client-scopes', ['client' => 'maestros-backend'])
            ->expectsOutputToContain('Application: api-maestros')
            ->assertSuccessful();

        $this->assertSame($maestros->id, (int) Client::where('name', 'maestros-backend')->value('application_id'));
    }

    public function test_client_application_command_rejects_unknown_clients_and_applications(): void
    {
        $this->createClient('maestros-backend');

        $this->artisan('oauth:client-application', ['client' => 'nobody', 'application' => 'api-auth'])->assertFailed();
        $this->artisan('oauth:client-application', ['client' => 'maestros-backend', 'application' => 'ghost'])->assertFailed();
    }

    public function test_role_scopes_command_lists_the_scopes_of_each_role_by_application(): void
    {
        $maestros = Application::factory()->create(['name' => 'api-maestros']);
        $portal = Application::factory()->create(['name' => 'portal']);
        OAuthScope::factory()->for($portal)->create(['name' => 'portal:ventas.crear']);
        OAuthScope::factory()->for($maestros)->create(['name' => 'geografia:leer']);
        Role::factory()->for($portal)->withScopes(['portal:ventas.crear', 'geografia:leer'])->create(['name' => 'vendedor']);

        $this->artisan('oauth:role-scopes', ['application' => 'portal'])
            ->expectsTable(['Role', 'Application', 'Scopes'], [
                ['vendedor', 'api-maestros', 'geografia:leer'],
                ['vendedor', 'portal', 'portal:ventas.crear'],
            ])
            ->assertSuccessful();

        $this->artisan('oauth:role-scopes', ['application' => 'ghost'])->assertFailed();
    }

    public function test_the_seeder_can_run_again_without_losing_extra_grants(): void
    {
        $admin = Role::firstWhere('name', 'admin');
        $scope = OAuthScope::factory()->create(['name' => 'extra:leer']);
        $admin->oauthScopes()->attach($scope);

        $this->seed(RoleScopeSeeder::class);

        $this->assertSame(1, Application::where('name', 'api-auth')->count());
        $this->assertSame(3, Role::count());
        $this->assertEqualsCanonicalizing(['extra:leer', 'user:read', 'user:write'], $admin->oauthScopes()->pluck('name')->all());
    }
}
