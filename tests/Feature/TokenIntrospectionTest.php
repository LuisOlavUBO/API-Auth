<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class TokenIntrospectionTest extends TestCase
{
    use RefreshDatabase;

    protected string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        $passwordClient = app(ClientRepository::class)->createPasswordGrantClient('Test Password Client', 'users', true);

        config([
            'oauth.password_client.id' => $passwordClient->getKey(),
            'oauth.password_client.secret' => $passwordClient->plainSecret,
        ]);

        $this->seed(RoleScopeSeeder::class);

        User::factory()->for(Role::firstWhere('name', 'user'))->create(['email' => 'ana@example.com', 'password' => 'secret-password']);

        $this->userToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@example.com',
            'password' => 'secret-password',
        ])->json('access_token');
    }

    protected function serviceToken(string $scope = 'introspect'): string
    {
        $client = app(ClientRepository::class)->createClientCredentialsGrantClient('Orders Service');

        Role::factory()->withScopes([$scope])->create()->clients()->attach($client->getKey());

        return $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => $scope,
        ])->assertOk()->json('access_token');
    }

    public function test_service_can_introspect_an_active_user_token(): void
    {
        $this->withToken($this->serviceToken())
            ->postJson('/api/v1/auth/introspect', ['token' => $this->userToken])
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonStructure(['client_id', 'sub', 'scope', 'exp']);
    }

    public function test_service_can_introspect_an_active_service_token(): void
    {
        $serviceToken = $this->serviceToken();

        $this->withToken($serviceToken)
            ->postJson('/api/v1/auth/introspect', ['token' => $serviceToken])
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('sub', null)
            ->assertJsonPath('user', null)
            ->assertJsonPath('scope', 'introspect');
    }

    public function test_revoked_or_invalid_tokens_are_inactive(): void
    {
        $serviceToken = $this->serviceToken();

        $this->withToken($this->userToken)->postJson('/api/v1/auth/logout')->assertOk();

        // Guards are cached across requests inside one test; reset like a new HTTP request would.
        $this->app['auth']->forgetGuards();

        $this->withToken($serviceToken)
            ->postJson('/api/v1/auth/introspect', ['token' => $this->userToken])
            ->assertOk()
            ->assertExactJson(['active' => false]);

        $this->withToken($serviceToken)
            ->postJson('/api/v1/auth/introspect', ['token' => 'garbage'])
            ->assertExactJson(['active' => false]);
    }

    public function test_introspection_requires_the_introspect_scope(): void
    {
        $this->withToken($this->serviceToken('user:read'))
            ->postJson('/api/v1/auth/introspect', ['token' => $this->userToken])
            ->assertForbidden();
    }

    public function test_user_tokens_cannot_call_introspection(): void
    {
        $this->withToken($this->userToken)
            ->postJson('/api/v1/auth/introspect', ['token' => $this->userToken])
            ->assertUnauthorized();
    }

    public function test_public_key_is_exposed(): void
    {
        $this->get('/api/v1/auth/public-key')
            ->assertOk()
            ->assertSee('BEGIN PUBLIC KEY');
    }
}
