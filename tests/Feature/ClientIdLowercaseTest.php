<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * Change client-id-en-minusculas: SQL Server returns uniqueidentifier in uppercase, but the client id goes
 * in lowercase in every token and in the introspection.
 */
class ClientIdLowercaseTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);

        $this->client = $this->createClient('sistema-x');

        $maestros = Application::factory()->create(['name' => 'api-maestros']);
        OAuthScope::factory()->for($maestros)->create(['name' => 'geografia:leer']);
        Role::factory()->for($maestros)->withScopes(['geografia:leer'])->create()->clients()->attach($this->client->getKey());
        Role::firstWhere('name', 'introspector')?->clients()->attach($this->client->getKey());
    }

    /** The client's id as API-IAM keeps it: lowercase. */
    protected function id(): string
    {
        return strtolower($this->client->getKey());
    }

    /**
     * @param  array<string, string>  $parameters
     */
    protected function clientToken(array $parameters, ?string $clientId = null): TestResponse
    {
        return $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId ?? $this->client->getKey(),
            'client_secret' => $this->client->plainSecret,
            ...$parameters,
        ]);
    }

    public function test_sql_server_returns_the_id_in_uppercase(): void
    {
        $this->assertSame(strtoupper($this->id()), (string) DB::table('oauth_clients')->where('id', $this->id())->value('id'));
    }

    public function test_a_client_token_carries_the_id_in_lowercase_even_if_asked_in_uppercase(): void
    {
        $token = $this->clientToken(['scope' => 'geografia:leer'], strtoupper($this->id()))->assertOk()->json('access_token');

        $claims = $this->claimsOf($token);
        $this->assertSame([$this->id()], (array) $claims['aud']);
        $this->assertSame($this->id(), $claims['sub']);
    }

    public function test_a_token_for_an_api_names_the_client_in_lowercase(): void
    {
        $token = $this->clientToken(['api' => 'api-maestros'])->assertOk()->json('access_token');

        $this->assertSame([$this->id(), 'api-maestros'], $this->claimsOf($token)['aud']);
    }

    public function test_the_introspection_answers_the_client_id_in_lowercase(): void
    {
        $token = $this->clientToken(['scope' => 'introspect'])->assertOk()->json('access_token');

        $this->withToken($token)
            ->postJson('/api/auth/v1/introspect', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('client_id', $this->id())
            ->assertJsonPath('aud.0', $this->id());
    }

    public function test_a_person_renews_the_token_and_it_names_the_client_in_lowercase(): void
    {
        $portal = $this->createCodeClient();
        $ana = User::factory()->withRoles(Role::firstWhere('name', 'user'))->create(['email' => 'ana@example.com', 'password' => 'secret-password']);
        $tokens = $this->tokensFor($ana, $portal);

        $this->assertSame(strtolower($portal->getKey()), ((array) $this->claimsOf($tokens['access_token'])['aud'])[0]);

        $renewed = $this->refreshTokens($portal, $tokens['refresh_token'])->assertOk()->json('access_token');
        $this->assertSame(strtolower($portal->getKey()), ((array) $this->claimsOf($renewed)['aud'])[0]);
    }
}
