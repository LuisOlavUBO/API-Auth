<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class ProblemDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);

        User::factory()->withRoles(Role::firstWhere('name', 'user'))->create([
            'email' => 'ana@example.com',
            'password' => 'secret-password',
        ]);
    }

    protected function assertProblem(TestResponse $response, int $status, string $error): TestResponse
    {
        $response->assertStatus($status)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'about:blank')
            ->assertJsonPath('status', $status)
            ->assertJsonPath('error', $error)
            ->assertJsonStructure(['title', 'detail']);

        return $response;
    }

    public function test_requests_without_a_token_get_a_problem(): void
    {
        $this->assertProblem($this->getJson('/api/auth/v1/me'), 401, 'NO_AUTENTICADO');
    }

    public function test_requests_without_a_token_and_without_accept_still_get_a_problem(): void
    {
        $this->assertProblem($this->get('/api/auth/v1/me'), 401, 'NO_AUTENTICADO');
        $this->assertProblem($this->withToken('invalid.token.value')->get('/api/auth/v1/me'), 401, 'NO_AUTENTICADO');
    }

    public function test_tokens_without_the_required_scope_get_a_problem(): void
    {
        $token = $this->tokensFor(User::first(), $this->createCodeClient(), 'user:write')['access_token'];

        $this->assertProblem($this->withToken($token)->getJson('/api/auth/v1/me'), 403, 'SCOPE_INSUFICIENTE')
            ->assertJsonPath('detail', 'El token no tiene el scope requerido: user:read.');
    }

    public function test_invalid_input_lists_the_errors_per_field(): void
    {
        $client = $this->createClient('Orders Service');
        Role::firstWhere('name', 'introspector')->clients()->attach($client->getKey());
        $serviceToken = $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => 'introspect',
        ])->assertOk()->json('access_token');

        $this->assertProblem($this->withToken($serviceToken)->postJson('/api/auth/v1/introspect'), 422, 'VALIDACION')
            ->assertJsonValidationErrors(['token'], 'errores');
    }

    public function test_a_reused_refresh_token_is_not_logged_as_an_error(): void
    {
        $client = $this->createCodeClient();
        $refreshToken = $this->tokensFor(User::first(), $client)['refresh_token'];
        $this->refreshTokens($client, $refreshToken)->assertOk();

        Log::spy();

        $this->refreshTokens($client, $refreshToken)->assertJsonPath('error', 'invalid_grant');

        Log::shouldNotHaveReceived('error');
    }

    public function test_unknown_routes_get_a_problem(): void
    {
        $this->assertProblem($this->getJson('/api/auth/v1/does-not-exist'), 404, 'RUTA_NO_ENCONTRADA');
    }

    public function test_unexpected_errors_do_not_expose_the_exception(): void
    {
        Route::get('api/auth/v1/broken', fn () => throw new RuntimeException('secret internal detail'));

        $this->assertProblem($this->getJson('/api/auth/v1/broken'), 500, 'ERROR_INTERNO')
            ->assertDontSee('secret internal detail')
            ->assertJsonMissingPath('trace');
    }

    /**
     * UBO.Common reads "error" and "error_description" from /api/auth/oauth/token.
     */
    public function test_the_token_endpoint_keeps_the_oauth2_error_format(): void
    {
        $client = $this->createClient('Orders Service');

        $response = $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => 'wrong-secret',
        ])->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_client')
            ->assertJsonStructure(['error', 'error_description'])
            ->assertJsonMissingPath('type');

        $this->assertStringStartsWith('application/json', $response->headers->get('Content-Type'));
    }

    /**
     * UBO.Common renews its own token when introspection answers 401.
     */
    public function test_introspection_without_a_service_token_still_answers_401(): void
    {
        $this->assertProblem($this->postJson('/api/auth/v1/introspect', ['token' => 'any']), 401, 'NO_AUTENTICADO');
    }
}
