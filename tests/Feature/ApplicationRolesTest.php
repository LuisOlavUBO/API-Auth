<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * Roles and clients belong to an application: what a user, or a client, may carry
 * in a token depends on the application of the client that obtains it.
 */
class ApplicationRolesTest extends TestCase
{
    use RefreshDatabase;

    protected Application $portal;

    protected Application $maestros;

    protected Role $vendedor;

    protected User $ana;

    protected Client $portalClient;

    protected function setUp(): void
    {
        parent::setUp();

        $login = $this->createClient('Test Password Client', 'password');
        config([
            'oauth.password_client.id' => $login->getKey(),
            'oauth.password_client.secret' => $login->plainSecret,
        ]);

        $this->seed(RoleScopeSeeder::class);

        $this->portal = Application::factory()->create(['name' => 'portal']);
        $this->maestros = Application::factory()->create(['name' => 'api-maestros']);

        OAuthScope::factory()->for($this->portal)->create(['name' => 'portal:ventas.crear']);
        OAuthScope::factory()->for($this->portal)->create(['name' => 'portal:ventas.anular']);
        OAuthScope::factory()->for($this->maestros)->create(['name' => 'geografia:leer']);

        $this->vendedor = Role::factory()->for($this->portal)->withScopes(['portal:ventas.crear', 'geografia:leer'])->create(['name' => 'vendedor']);

        $this->ana = User::factory()
            ->withRoles($this->vendedor, Role::firstWhere('name', 'user'))
            ->create(['email' => 'ana@example.com', 'password' => 'secret-password']);

        $this->portalClient = $this->createClient('portal-web', 'password', 'portal');
    }

    protected function passwordToken(Client $client, ?string $scope = null, string $email = 'ana@example.com'): TestResponse
    {
        return $this->postJson('/api/auth/oauth/token', array_filter([
            'grant_type' => 'password',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'username' => $email,
            'password' => 'secret-password',
            'scope' => $scope,
        ]));
    }

    protected function refreshToken(Client $client, string $refreshToken): TestResponse
    {
        return $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function claimsOf(string $accessToken): array
    {
        $payload = explode('.', $accessToken)[1];

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    }

    public function test_a_role_name_is_unique_inside_its_application_only(): void
    {
        Role::factory()->for($this->maestros)->create(['name' => 'lector']);
        Role::factory()->for($this->portal)->create(['name' => 'lector']);

        $this->assertSame(2, Role::where('name', 'lector')->count());

        $this->expectException(QueryException::class);
        Role::factory()->for($this->portal)->create(['name' => 'lector']);
    }

    public function test_allowed_scopes_are_the_ones_of_the_roles_in_one_application(): void
    {
        $this->assertSame(['geografia:leer', 'portal:ventas.crear'], $this->ana->allowedScopeNames($this->portal->id));
        $this->assertSame(
            ['user:read', 'user:write'],
            $this->ana->allowedScopeNames(Application::firstWhere('name', 'api-auth')->id),
        );
    }

    public function test_a_user_with_roles_in_two_applications_gets_only_those_of_the_client_application(): void
    {
        $login = $this->postJson('/api/auth/v1/login', ['email' => 'ana@example.com', 'password' => 'secret-password', 'scope' => 'user:read user:write'])
            ->assertOk()->json('access_token');

        $this->assertSame(['user:read', 'user:write'], $this->claimsOf($login)['scopes']);
        $this->assertSame(['user'], $this->claimsOf($login)['roles']);
    }

    public function test_a_token_without_scope_carries_all_the_scopes_of_the_roles_in_the_client_application(): void
    {
        $claims = $this->claimsOf($this->passwordToken($this->portalClient)->assertOk()->json('access_token'));

        $this->assertEqualsCanonicalizing(['portal:ventas.crear', 'geografia:leer'], $claims['scopes']);
        $this->assertSame(['vendedor'], $claims['roles']);
    }

    public function test_the_audience_names_the_applications_that_own_the_scopes(): void
    {
        $claims = $this->claimsOf($this->passwordToken($this->portalClient)->assertOk()->json('access_token'));

        $this->assertSame([strtolower($this->portalClient->getKey()), 'api-maestros', 'portal'], array_map('strtolower', (array) $claims['aud']));
    }

    public function test_a_user_without_roles_in_the_application_gets_a_token_without_scopes(): void
    {
        User::factory()->create(['email' => 'beto@example.com', 'password' => 'secret-password']);

        $claims = $this->claimsOf($this->passwordToken($this->portalClient, null, 'beto@example.com')->assertOk()->json('access_token'));

        $this->assertSame([], $claims['scopes']);
        $this->assertSame([], $claims['roles']);
        $this->assertSame([strtolower($this->portalClient->getKey())], array_map('strtolower', (array) $claims['aud']));
    }

    public function test_a_scope_of_a_role_in_another_application_is_rejected(): void
    {
        $login = $this->createClient('Another login', 'password');

        $this->passwordToken($login, 'portal:ventas.crear')
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_scope');
    }

    public function test_a_requested_scope_limits_the_token(): void
    {
        $claims = $this->claimsOf($this->passwordToken($this->portalClient, 'geografia:leer')->assertOk()->json('access_token'));

        $this->assertSame(['geografia:leer'], $claims['scopes']);
        $this->assertSame(['vendedor'], $claims['roles']);
    }

    public function test_refresh_drops_scopes_but_never_adds_them(): void
    {
        $refresh = $this->passwordToken($this->portalClient, 'geografia:leer')->assertOk()->json('refresh_token');

        $this->vendedor->oauthScopes()->attach(OAuthScope::firstWhere('name', 'portal:ventas.anular'));

        $claims = $this->claimsOf($this->refreshToken($this->portalClient, $refresh)->assertOk()->json('access_token'));

        $this->assertSame(['geografia:leer'], $claims['scopes']);
    }

    public function test_refresh_drops_the_scopes_of_a_role_that_was_removed(): void
    {
        $refresh = $this->passwordToken($this->portalClient)->assertOk()->json('refresh_token');

        $this->ana->roles()->detach($this->vendedor);

        $claims = $this->claimsOf($this->refreshToken($this->portalClient, $refresh)->assertOk()->json('access_token'));

        $this->assertSame([], $claims['scopes']);
        $this->assertSame([], $claims['roles']);
    }

    public function test_refresh_recalculates_the_roles_and_keeps_the_scopes(): void
    {
        $refresh = $this->passwordToken($this->portalClient)->assertOk()->json('refresh_token');

        $supervisor = Role::factory()->for($this->portal)->withScopes(['portal:ventas.anular'])->create(['name' => 'supervisor']);
        $this->ana->roles()->attach($supervisor);

        $claims = $this->claimsOf($this->refreshToken($this->portalClient, $refresh)->assertOk()->json('access_token'));

        $this->assertEqualsCanonicalizing(['portal:ventas.crear', 'geografia:leer'], $claims['scopes']);
        $this->assertEqualsCanonicalizing(['supervisor', 'vendedor'], $claims['roles']);
    }

    public function test_a_user_without_scopes_does_not_get_scopes_on_refresh(): void
    {
        User::factory()->create(['email' => 'beto@example.com', 'password' => 'secret-password']);
        $beto = User::firstWhere('email', 'beto@example.com');

        $refresh = $this->passwordToken($this->portalClient, null, 'beto@example.com')->assertOk()->json('refresh_token');
        $beto->roles()->attach($this->vendedor);

        $claims = $this->claimsOf($this->refreshToken($this->portalClient, $refresh)->assertOk()->json('access_token'));

        $this->assertSame([], $claims['scopes']);
    }

    public function test_a_user_token_carries_the_user_uuid_and_keeps_the_id_as_subject(): void
    {
        $login = $this->passwordToken($this->portalClient)->assertOk();
        $claims = $this->claimsOf($login->json('access_token'));
        $refreshed = $this->claimsOf($this->refreshToken($this->portalClient, $login->json('refresh_token'))->assertOk()->json('access_token'));

        $this->assertSame($this->ana->uuid, $claims['uuid']);
        $this->assertSame((string) $this->ana->id, $claims['sub']);
        $this->assertSame($this->ana->uuid, $refreshed['uuid']);
    }

    public function test_a_client_with_a_role_of_another_application_gets_a_token_for_that_api(): void
    {
        $backend = $this->createClient('portal-backend', 'client-credentials', 'portal');
        $lector = Role::factory()->for($this->maestros)->withScopes(['geografia:leer'])->create(['name' => 'lector']);
        $consulta = Role::factory()->for(Application::factory()->create(['name' => 'api-matricula']))
            ->withScopes(['matricula:leer'])->create(['name' => 'consulta']);
        $lector->clients()->attach($backend->getKey());
        $consulta->clients()->attach($backend->getKey());

        $token = $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $backend->getKey(),
            'client_secret' => $backend->plainSecret,
            'api' => 'api-maestros',
        ])->assertOk()->json('access_token');

        $claims = $this->claimsOf($token);

        $this->assertSame(['geografia:leer'], $claims['scopes']);
        $this->assertSame(['lector'], $claims['roles']);
        $this->assertSame([strtolower($backend->getKey()), 'api-maestros'], array_map('strtolower', (array) $claims['aud']));
        $this->assertArrayNotHasKey('uuid', $claims);
    }

    public function test_a_client_without_api_and_scope_gets_a_token_without_scopes(): void
    {
        $backend = $this->createClient('portal-backend', 'client-credentials', 'portal');
        Role::factory()->for($this->maestros)->withScopes(['user:read', 'geografia:leer'])->create(['name' => 'lector'])
            ->clients()->attach($backend->getKey());

        $token = $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $backend->getKey(),
            'client_secret' => $backend->plainSecret,
        ])->assertOk()->json('access_token');

        $claims = $this->claimsOf($token);

        $this->assertSame([], $claims['scopes']);
        $this->assertSame([], $claims['roles']);
    }

    public function test_login_keeps_giving_the_default_scope(): void
    {
        $admin = Role::firstWhere('name', 'admin');
        $this->ana->roles()->attach($admin);

        $login = $this->postJson('/api/auth/v1/login', ['email' => 'ana@example.com', 'password' => 'secret-password'])
            ->assertOk()->json('access_token');

        $this->assertSame(['user:read'], $this->claimsOf($login)['scopes']);
        $this->assertSame([$this->claimsOf($login)['aud'][0], 'api-auth'], (array) $this->claimsOf($login)['aud']);
    }
}
