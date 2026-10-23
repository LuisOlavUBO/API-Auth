<?php

namespace Tests\Feature;

use App\Models\Api;
use App\Models\OAuthScope;
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

        $maestros = Api::factory()->create(['name' => 'api-maestros']);
        $matricula = Api::factory()->create(['name' => 'api-matricula']);

        foreach (['geografia:leer', 'sistemas:leer', 'homologacion:leer'] as $name) {
            OAuthScope::factory()->for($maestros)->create(['name' => $name]);
        }

        OAuthScope::factory()->for($matricula)->create(['name' => 'matricula:leer']);

        Role::factory()->withScopes(['geografia:leer', 'sistemas:leer', 'homologacion:leer'])->create(['name' => 'maestros-lector']);
        Role::factory()->withScopes(['matricula:leer'])->create(['name' => 'matricula-lector']);
    }

    protected function requestToken(?string $scope, ?string $api = null): TestResponse
    {
        return $this->postJson('/oauth/token', array_filter([
            'grant_type' => 'client_credentials',
            'client_id' => $this->client->getKey(),
            'client_secret' => $this->client->plainSecret,
            'scope' => $scope,
            'api' => $api,
        ]));
    }

    /**
     * @return string[]
     */
    protected function scopesOf(string $accessToken): array
    {
        return $this->claimsOf($accessToken)['scopes'];
    }

    /**
     * @return string[]
     */
    protected function audiencesOf(string $accessToken): array
    {
        return (array) $this->claimsOf($accessToken)['aud'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function claimsOf(string $accessToken): array
    {
        $payload = explode('.', $accessToken)[1];

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
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

    public function test_token_for_an_api_carries_only_the_client_scopes_of_that_api(): void
    {
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['maestros-lector', 'matricula-lector']]);

        $maestros = $this->requestToken(null, 'api-maestros')->assertOk()->json('access_token');
        $matricula = $this->requestToken(null, 'api-matricula')->assertOk()->json('access_token');

        $this->assertSame(['geografia:leer', 'homologacion:leer', 'sistemas:leer'], $this->scopesOf($maestros));
        $this->assertSame(['matricula:leer'], $this->scopesOf($matricula));
    }

    public function test_token_for_an_api_names_it_in_the_audience_after_the_client_id(): void
    {
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['maestros-lector']]);

        $forApi = $this->requestToken(null, 'api-maestros')->assertOk()->json('access_token');
        $withoutApi = $this->requestToken('geografia:leer')->assertOk()->json('access_token');

        // SQL Server returns uniqueidentifier keys in upper case: the token carries the stored id.
        $clientId = $this->client->fresh()->getKey();

        $this->assertSame([$clientId, 'api-maestros'], $this->audiencesOf($forApi));
        $this->assertSame([$clientId], $this->audiencesOf($withoutApi));
    }

    public function test_token_for_an_api_can_be_narrowed_to_some_of_its_scopes(): void
    {
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['maestros-lector', 'matricula-lector']]);

        $token = $this->requestToken('geografia:leer', 'api-maestros')->assertOk()->json('access_token');

        $this->assertSame(['geografia:leer'], $this->scopesOf($token));
        $this->requestToken('matricula:leer', 'api-maestros')->assertBadRequest()->assertJsonPath('error', 'invalid_scope');
    }

    public function test_token_for_an_api_where_the_client_has_no_scopes_is_rejected(): void
    {
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['maestros-lector']]);

        $this->requestToken(null, 'api-matricula')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_scope')
            ->assertJsonPath('error_description', 'The client has no scopes in API [api-matricula].');
    }

    public function test_token_for_an_unknown_api_is_rejected(): void
    {
        $this->artisan('oauth:client-role', ['client' => 'sistema-x', 'roles' => ['maestros-lector']]);

        $this->requestToken(null, 'api-bancos')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request')
            ->assertJsonPath('error_description', 'Unknown API [api-bancos].');
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
