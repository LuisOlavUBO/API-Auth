<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->portalClient = $this->createCodeClient('portal');
    }

    /**
     * Tokens of a user through the central login.
     *
     * @return array<string, mixed>
     */
    protected function userTokens(Client $client, ?string $scope = null, string $email = 'ana@example.com'): array
    {
        return $this->tokensFor(User::firstWhere('email', $email), $client, $scope);
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
        $login = $this->userTokens($this->createCodeClient(name: 'auth-web'), 'user:read user:write')['access_token'];

        $this->assertSame(['user:read', 'user:write'], $this->claimsOf($login)['scopes']);
        $this->assertSame(['user'], $this->claimsOf($login)['roles']);
    }

    public function test_a_token_without_scope_carries_all_the_scopes_of_the_roles_in_the_client_application(): void
    {
        $claims = $this->claimsOf($this->userTokens($this->portalClient)['access_token']);

        $this->assertEqualsCanonicalizing(['portal:ventas.crear', 'geografia:leer'], $claims['scopes']);
        $this->assertSame(['vendedor'], $claims['roles']);
    }

    public function test_the_audience_names_the_applications_that_own_the_scopes(): void
    {
        $claims = $this->claimsOf($this->userTokens($this->portalClient)['access_token']);

        $this->assertSame([strtolower($this->portalClient->getKey()), 'api-maestros', 'portal'], array_map('strtolower', (array) $claims['aud']));
    }

    public function test_a_scope_of_a_role_in_another_application_is_rejected(): void
    {
        $client = $this->createCodeClient(name: 'auth-web');
        $code = $this->queryOf($this->actingAs($this->ana)->get($this->authorizeUrl($client, ['scope' => 'portal:ventas.crear'])))['code'];

        $this->exchange($client, $code)
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_scope')
            ->assertJsonMissingPath('access_token');
    }

    public function test_a_requested_scope_limits_the_token(): void
    {
        $claims = $this->claimsOf($this->userTokens($this->portalClient, 'geografia:leer')['access_token']);

        $this->assertSame(['geografia:leer'], $claims['scopes']);
        $this->assertSame(['vendedor'], $claims['roles']);
    }

    public function test_refresh_drops_scopes_but_never_adds_them(): void
    {
        $refresh = $this->userTokens($this->portalClient, 'geografia:leer')['refresh_token'];

        $this->vendedor->oauthScopes()->attach(OAuthScope::firstWhere('name', 'portal:ventas.anular'));

        $claims = $this->claimsOf($this->refreshTokens($this->portalClient, $refresh)->assertOk()->json('access_token'));

        $this->assertSame(['geografia:leer'], $claims['scopes']);
    }

    public function test_refresh_drops_the_scopes_of_a_role_that_was_removed(): void
    {
        $refresh = $this->userTokens($this->portalClient)['refresh_token'];

        $this->ana->roles()->detach($this->vendedor);

        $claims = $this->claimsOf($this->refreshTokens($this->portalClient, $refresh)->assertOk()->json('access_token'));

        $this->assertSame([], $claims['scopes']);
        $this->assertSame([], $claims['roles']);
    }

    public function test_refresh_recalculates_the_roles_and_keeps_the_scopes(): void
    {
        $refresh = $this->userTokens($this->portalClient)['refresh_token'];

        $supervisor = Role::factory()->for($this->portal)->withScopes(['portal:ventas.anular'])->create(['name' => 'supervisor']);
        $this->ana->roles()->attach($supervisor);

        $claims = $this->claimsOf($this->refreshTokens($this->portalClient, $refresh)->assertOk()->json('access_token'));

        $this->assertEqualsCanonicalizing(['portal:ventas.crear', 'geografia:leer'], $claims['scopes']);
        $this->assertEqualsCanonicalizing(['supervisor', 'vendedor'], $claims['roles']);
    }

    public function test_a_user_without_scopes_does_not_get_scopes_on_refresh(): void
    {
        $sinPermisos = Role::factory()->for($this->portal)->create(['name' => 'sin-permisos']);
        $beto = User::factory()->withRoles($sinPermisos)->create(['email' => 'beto@example.com', 'password' => 'secret-password']);

        $refresh = $this->userTokens($this->portalClient, null, 'beto@example.com')['refresh_token'];
        $beto->roles()->attach($this->vendedor);

        $claims = $this->claimsOf($this->refreshTokens($this->portalClient, $refresh)->assertOk()->json('access_token'));

        $this->assertSame([], $claims['scopes']);
    }

    public function test_a_user_token_carries_the_user_uuid_and_keeps_the_id_as_subject(): void
    {
        $login = $this->userTokens($this->portalClient);
        $claims = $this->claimsOf($login['access_token']);
        $refreshed = $this->claimsOf($this->refreshTokens($this->portalClient, $login['refresh_token'])->assertOk()->json('access_token'));

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

    public function test_a_disabled_role_grants_no_scopes_nor_role_until_it_is_reactivated(): void
    {
        $supervisor = Role::factory()->for($this->portal)->withScopes(['portal:ventas.anular'])->create(['name' => 'supervisor']);
        $this->ana->roles()->attach($supervisor);

        $this->vendedor->forceFill(['disabled_at' => now()])->save();
        $claims = $this->claimsOf($this->userTokens($this->portalClient)['access_token']);

        $this->assertSame(['portal:ventas.anular'], $claims['scopes']);
        $this->assertSame(['supervisor'], $claims['roles']);

        $this->vendedor->forceFill(['disabled_at' => null])->save();
        $claims = $this->claimsOf($this->userTokens($this->portalClient)['access_token']);

        $this->assertEqualsCanonicalizing(['portal:ventas.anular', 'portal:ventas.crear', 'geografia:leer'], $claims['scopes']);
        $this->assertSame(['supervisor', 'vendedor'], $claims['roles']);
    }

    public function test_a_disabled_role_grants_no_scopes_to_a_client_until_it_is_reactivated(): void
    {
        $backend = $this->createClient('portal-backend', 'client-credentials', 'portal');
        $lector = Role::factory()->for($this->maestros)->withScopes(['geografia:leer'])->create(['name' => 'lector']);
        $lector->clients()->attach($backend->getKey());

        $request = [
            'grant_type' => 'client_credentials',
            'client_id' => $backend->getKey(),
            'client_secret' => $backend->plainSecret,
            'scope' => 'geografia:leer',
        ];

        $lector->forceFill(['disabled_at' => now()])->save();

        $this->postJson('/api/auth/oauth/token', $request)
            ->assertJsonPath('error', 'invalid_scope')
            ->assertJsonMissingPath('access_token');

        $lector->forceFill(['disabled_at' => null])->save();
        $claims = $this->claimsOf($this->postJson('/api/auth/oauth/token', $request)->assertOk()->json('access_token'));

        $this->assertSame(['geografia:leer'], $claims['scopes']);
        $this->assertSame(['lector'], $claims['roles']);
    }

    public function test_the_scopes_of_a_disabled_application_are_not_granted(): void
    {
        $this->maestros->forceFill(['disabled_at' => now()])->save();

        $claims = $this->claimsOf($this->userTokens($this->portalClient)['access_token']);

        $this->assertSame(['portal:ventas.crear'], $claims['scopes']);
        $this->assertSame([strtolower($this->portalClient->getKey()), 'portal'], array_map('strtolower', (array) $claims['aud']));
    }

    public function test_the_roles_of_a_disabled_application_grant_no_scopes_to_a_client(): void
    {
        $backend = $this->createClient('maestros-backend', 'client-credentials', 'api-maestros');
        $this->vendedor->clients()->attach($backend->getKey());
        $this->portal->forceFill(['disabled_at' => now()])->save();

        $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $backend->getKey(),
            'client_secret' => $backend->plainSecret,
            'api' => 'api-maestros',
        ])->assertJsonPath('error', 'invalid_scope')->assertJsonMissingPath('access_token');
    }

    public function test_a_client_of_a_disabled_application_gets_no_token(): void
    {
        $backend = $this->createClient('portal-backend', 'client-credentials', 'portal');
        $this->portal->forceFill(['disabled_at' => now()])->save();

        $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $backend->getKey(),
            'client_secret' => $backend->plainSecret,
        ])->assertUnauthorized()->assertJsonPath('error', 'invalid_client')->assertJsonMissingPath('access_token');
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
}
