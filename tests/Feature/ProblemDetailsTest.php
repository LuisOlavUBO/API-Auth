<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProblemDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $client = app(ClientRepository::class)->createPasswordGrantClient('Test Password Client', 'users', true);

        config([
            'oauth.password_client.id' => $client->getKey(),
            'oauth.password_client.secret' => $client->plainSecret,
        ]);

        $this->seed(RoleScopeSeeder::class);

        User::factory()->for(Role::firstWhere('name', 'user'))->create([
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

    public function test_tokens_without_the_required_scope_get_a_problem(): void
    {
        $token = $this->postJson('/api/auth/v1/login', [
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'scope' => 'user:write',
        ])->json('access_token');

        $this->assertProblem($this->withToken($token)->getJson('/api/auth/v1/me'), 403, 'SCOPE_INSUFICIENTE')
            ->assertJsonPath('detail', 'El token no tiene el scope requerido: user:read.');
    }

    public function test_invalid_input_lists_the_errors_per_field(): void
    {
        $this->assertProblem($this->postJson('/api/auth/v1/login', ['email' => 'not-an-email']), 422, 'VALIDACION')
            ->assertJsonValidationErrors(['email', 'password'], 'errores');
    }

    public function test_login_failures_keep_their_oauth_code(): void
    {
        $this->assertProblem($this->postJson('/api/auth/v1/login', [
            'email' => 'ana@example.com',
            'password' => 'wrong-password',
        ]), 401, 'invalid_grant')->assertJsonPath('title', 'Credenciales inválidas');
    }

    public function test_failed_logins_are_not_logged_as_errors(): void
    {
        Log::spy();

        $this->postJson('/api/auth/v1/login', ['email' => 'ana@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized();

        Log::shouldNotHaveReceived('error');
    }

    public function test_a_rejected_password_client_answers_500_and_logs_the_reason(): void
    {
        config(['oauth.password_client.secret' => 'wrong-secret']);
        Log::spy();

        $this->assertProblem($this->postJson('/api/auth/v1/login', [
            'email' => 'ana@example.com',
            'password' => 'secret-password',
        ]), 500, 'invalid_client')
            ->assertJsonPath('detail', 'El servicio de autenticación no está bien configurado.')
            ->assertDontSee('Client authentication failed');

        Log::shouldHaveReceived('error')->once()
            ->with(Mockery::on(fn (string $message) => str_starts_with($message, 'The password grant client was rejected: ')));
    }

    public function test_the_rate_limit_answers_a_problem_with_retry_after(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/auth/v1/login', ['email' => 'ana@example.com', 'password' => 'wrong-password']);
        }

        $this->assertProblem(
            $this->postJson('/api/auth/v1/login', ['email' => 'ana@example.com', 'password' => 'wrong-password']),
            429,
            'LIMITE_EXCEDIDO',
        )->assertHeader('Retry-After');
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
        $client = app(ClientRepository::class)->createClientCredentialsGrantClient('Orders Service');

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
