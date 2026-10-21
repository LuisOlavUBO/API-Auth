<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ClientScopesTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);

        $this->client = app(ClientRepository::class)->createClientCredentialsGrantClient('sistema-x');

        Role::factory()->withScopes(['geografia:leer', 'sistemas:leer', 'homologacion:leer'])->create(['name' => 'maestros-lector']);
        Role::factory()->withScopes(['matricula:leer'])->create(['name' => 'matricula-lector']);
    }

    protected function requestToken(?string $scope): TestResponse
    {
        return $this->postJson('/oauth/token', array_filter([
            'grant_type' => 'client_credentials',
            'client_id' => $this->client->getKey(),
            'client_secret' => $this->client->plainSecret,
            'scope' => $scope,
        ]));
    }

    /**
     * @return string[]
     */
    protected function scopesOf(string $accessToken): array
    {
        $payload = explode('.', $accessToken)[1];

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true)['scopes'];
    }

    public function test_client_without_roles_cannot_request_scopes(): void
    {
        $this->requestToken('introspect')->assertBadRequest()->assertJsonPath('error', 'invalid_scope');
    }

    public function test_client_without_roles_gets_a_token_without_scopes(): void
    {
        $token = $this->requestToken(null)->assertOk()->json('access_token');

        $this->assertSame([], $this->scopesOf($token));
    }

    public function test_client_can_request_scopes_from_all_its_roles(): void
    {
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['maestros-lector', 'matricula-lector']])
            ->assertSuccessful();

        $token = $this->requestToken('geografia:leer matricula:leer')->assertOk()->json('access_token');

        $this->assertSame(['geografia:leer', 'matricula:leer'], $this->scopesOf($token));
    }

    public function test_client_cannot_request_scopes_outside_its_roles(): void
    {
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['maestros-lector']])
            ->assertSuccessful();

        $this->requestToken('matricula:leer')->assertBadRequest()->assertJsonPath('error', 'invalid_scope');
    }

    public function test_client_role_sync_replaces_previous_roles(): void
    {
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['maestros-lector']]);
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['matricula-lector'], '--sync' => true])
            ->assertSuccessful();

        $this->requestToken('geografia:leer')->assertBadRequest();
        $this->requestToken('matricula:leer')->assertOk();
    }

    public function test_client_scopes_command_lists_what_the_client_may_request(): void
    {
        $this->artisan('oauth:client-role', ['client' => $this->client->getKey(), 'roles' => ['maestros-lector', 'matricula-lector']]);

        $this->artisan('oauth:client-scopes', ['client' => 'sistema-x'])
            ->expectsOutputToContain('May request: geografia:leer homologacion:leer matricula:leer sistemas:leer')
            ->assertSuccessful();
    }

    public function test_client_role_command_rejects_unknown_clients_and_roles(): void
    {
        $this->artisan('oauth:client-role', ['client' => 'no-existe', 'roles' => ['maestros-lector']])->assertFailed();
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['ghost']])->assertFailed();
    }
}
