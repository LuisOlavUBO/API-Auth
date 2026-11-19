<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

class ClientScopesTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;

    protected Role $maestrosLector;

    protected Role $matriculaLector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);

        $this->client = $this->createClient('sistema-x');

        $maestros = Application::factory()->create(['name' => 'api-maestros']);
        $matricula = Application::factory()->create(['name' => 'api-matricula']);

        foreach (['geografia:leer', 'sistemas:leer', 'homologacion:leer'] as $name) {
            OAuthScope::factory()->for($maestros)->create(['name' => $name]);
        }

        OAuthScope::factory()->for($matricula)->create(['name' => 'matricula:leer']);

        $this->maestrosLector = Role::factory()->for($maestros)->withScopes(['geografia:leer', 'sistemas:leer', 'homologacion:leer'])->create(['name' => 'maestros-lector']);
        $this->matriculaLector = Role::factory()->for($matricula)->withScopes(['matricula:leer'])->create(['name' => 'matricula-lector']);
    }

    /**
     * Give the client these roles, as API-IAM does (rows in oauth_client_role).
     */
    protected function giveRoles(Role ...$roles): void
    {
        foreach ($roles as $role) {
            $role->clients()->attach($this->client->getKey());
        }
    }

    protected function requestToken(?string $scope, ?string $api = null): TestResponse
    {
        return $this->postJson('/api/auth/oauth/token', array_filter([
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

    public function test_client_without_roles_cannot_request_scopes(): void
    {
        $this->requestToken('introspect')->assertBadRequest()->assertJsonPath('error', 'invalid_scope');
    }

    public function test_scopes_outside_the_catalog_are_rejected(): void
    {
        foreach (['does-not-exist', '*'] as $scope) {
            $this->requestToken($scope)->assertBadRequest()->assertJsonPath('error', 'invalid_scope');
        }
    }

    public function test_client_without_roles_gets_a_token_without_scopes(): void
    {
        $token = $this->requestToken(null)->assertOk()->json('access_token');

        $this->assertSame([], $this->scopesOf($token));
    }

    public function test_client_can_request_scopes_from_all_its_roles(): void
    {
        $this->giveRoles($this->maestrosLector, $this->matriculaLector);

        $token = $this->requestToken('geografia:leer matricula:leer')->assertOk()->json('access_token');

        $this->assertSame(['geografia:leer', 'matricula:leer'], $this->scopesOf($token));
    }

    public function test_client_cannot_request_scopes_outside_its_roles(): void
    {
        $this->giveRoles($this->maestrosLector);

        $this->requestToken('matricula:leer')->assertBadRequest()->assertJsonPath('error', 'invalid_scope');
    }

    public function test_token_for_an_api_carries_only_the_client_scopes_of_that_api(): void
    {
        $this->giveRoles($this->maestrosLector, $this->matriculaLector);

        $maestros = $this->requestToken(null, 'api-maestros')->assertOk()->json('access_token');
        $matricula = $this->requestToken(null, 'api-matricula')->assertOk()->json('access_token');

        $this->assertSame(['geografia:leer', 'homologacion:leer', 'sistemas:leer'], $this->scopesOf($maestros));
        $this->assertSame(['matricula:leer'], $this->scopesOf($matricula));
    }

    public function test_token_for_an_api_names_it_in_the_audience_after_the_client_id(): void
    {
        $this->giveRoles($this->maestrosLector);

        $forApi = $this->requestToken(null, 'api-maestros')->assertOk()->json('access_token');
        $withoutApi = $this->requestToken('geografia:leer')->assertOk()->json('access_token');

        // SQL Server returns uniqueidentifier keys in upper case: the token carries the stored id.
        $clientId = $this->client->fresh()->getKey();

        $this->assertSame([$clientId, 'api-maestros'], $this->audiencesOf($forApi));
        $this->assertSame([$clientId], $this->audiencesOf($withoutApi));
    }

    public function test_token_for_an_api_can_be_narrowed_to_some_of_its_scopes(): void
    {
        $this->giveRoles($this->maestrosLector, $this->matriculaLector);

        $token = $this->requestToken('geografia:leer', 'api-maestros')->assertOk()->json('access_token');

        $this->assertSame(['geografia:leer'], $this->scopesOf($token));
        $this->requestToken('matricula:leer', 'api-maestros')->assertBadRequest()->assertJsonPath('error', 'invalid_scope');
    }

    public function test_token_for_an_api_where_the_client_has_no_scopes_is_rejected(): void
    {
        $this->giveRoles($this->maestrosLector);

        $this->requestToken(null, 'api-matricula')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_scope')
            ->assertJsonPath('error_description', 'The client has no scopes in API [api-matricula].');
    }

    public function test_token_for_an_unknown_api_is_rejected(): void
    {
        $this->giveRoles($this->maestrosLector);

        $this->requestToken(null, 'api-bancos')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request')
            ->assertJsonPath('error_description', 'Unknown API [api-bancos].');
    }
}
