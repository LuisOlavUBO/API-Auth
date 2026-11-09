<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenIntrospectionTest extends TestCase
{
    use RefreshDatabase;

    protected string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);

        $ana = User::factory()->withRoles(Role::firstWhere('name', 'user'))->create(['email' => 'ana@example.com', 'password' => 'secret-password', 'rut' => '12345678-5']);

        $this->userToken = $this->tokensFor($ana, $this->createCodeClient())['access_token'];
    }

    protected function serviceToken(string $scope = 'introspect'): string
    {
        $client = $this->createClient('Orders Service');

        Role::factory()->withScopes([$scope])->create()->clients()->attach($client->getKey());

        return $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => $scope,
        ])->assertOk()->json('access_token');
    }

    public function test_service_can_introspect_an_active_user_token(): void
    {
        $this->withToken($this->serviceToken())
            ->postJson('/api/auth/v1/introspect', ['token' => $this->userToken])
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonPath('uuid', User::firstWhere('email', 'ana@example.com')->uuid)
            ->assertJsonPath('user.uuid', User::firstWhere('email', 'ana@example.com')->uuid)
            ->assertJsonPath('user.rut', '12345678-5')
            ->assertJsonStructure(['client_id', 'sub', 'scope', 'exp']);
    }

    public function test_service_can_introspect_an_active_service_token(): void
    {
        $serviceToken = $this->serviceToken();

        $this->withToken($serviceToken)
            ->postJson('/api/auth/v1/introspect', ['token' => $serviceToken])
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('sub', null)
            ->assertJsonPath('uuid', null)
            ->assertJsonPath('user', null)
            ->assertJsonPath('scope', 'introspect');
    }

    public function test_introspection_reports_the_api_a_token_was_issued_for(): void
    {
        $api = Application::factory()->create(['name' => 'api-maestros']);
        OAuthScope::factory()->for($api)->create(['name' => 'geografia:leer']);

        $client = $this->createClient('Sistema X');
        Role::factory()->withScopes(['geografia:leer'])->create()->clients()->attach($client->getKey());

        $apiToken = $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'api' => 'api-maestros',
        ])->assertOk()->json('access_token');

        $this->withToken($this->serviceToken())
            ->postJson('/api/auth/v1/introspect', ['token' => $apiToken])
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('aud', [$client->fresh()->getKey(), 'api-maestros'])
            ->assertJsonPath('scope', 'geografia:leer');
    }

    public function test_revoked_or_invalid_tokens_are_inactive(): void
    {
        $serviceToken = $this->serviceToken();

        $this->withToken($this->userToken)->postJson('/api/auth/v1/logout')->assertOk();

        // Guards are cached across requests inside one test; reset like a new HTTP request would.
        $this->app['auth']->forgetGuards();

        $this->withToken($serviceToken)
            ->postJson('/api/auth/v1/introspect', ['token' => $this->userToken])
            ->assertOk()
            ->assertExactJson(['active' => false]);

        $this->withToken($serviceToken)
            ->postJson('/api/auth/v1/introspect', ['token' => 'garbage'])
            ->assertExactJson(['active' => false]);
    }

    public function test_the_tokens_of_a_disabled_user_are_inactive(): void
    {
        User::firstWhere('email', 'ana@example.com')->forceFill(['disabled_at' => now()])->save();

        $this->withToken($this->serviceToken())
            ->postJson('/api/auth/v1/introspect', ['token' => $this->userToken])
            ->assertOk()
            ->assertExactJson(['active' => false]);
    }

    public function test_introspection_requires_the_introspect_scope(): void
    {
        $this->withToken($this->serviceToken('user:read'))
            ->postJson('/api/auth/v1/introspect', ['token' => $this->userToken])
            ->assertForbidden();
    }

    public function test_user_tokens_cannot_call_introspection(): void
    {
        $this->withToken($this->userToken)
            ->postJson('/api/auth/v1/introspect', ['token' => $this->userToken])
            ->assertUnauthorized();
    }

    public function test_introspection_reports_the_roles_of_a_user_token(): void
    {
        $this->withToken($this->serviceToken())
            ->postJson('/api/auth/v1/introspect', ['token' => $this->userToken])
            ->assertOk()
            ->assertJsonPath('roles', ['user']);
    }

    public function test_introspection_reports_the_roles_of_a_service_token(): void
    {
        $client = $this->createClient('Orders Service');
        Role::firstWhere('name', 'introspector')->clients()->attach($client->getKey());

        $token = $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => 'introspect',
        ])->assertOk()->json('access_token');

        $this->withToken($token)
            ->postJson('/api/auth/v1/introspect', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('roles', ['introspector']);
    }

    public function test_public_key_is_exposed(): void
    {
        $this->get('/api/auth/v1/public-key')
            ->assertOk()
            ->assertSee('BEGIN PUBLIC KEY');
    }
}
