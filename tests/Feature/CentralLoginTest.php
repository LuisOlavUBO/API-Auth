<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Client;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The central login: web login, authorization code with PKCE, token exchange and logout.
 */
class CentralLoginTest extends TestCase
{
    use RefreshDatabase;

    protected const REDIRECT_URI = 'https://portal.ubo.cl/callback';

    protected const VERIFIER = 'a-code-verifier-of-at-least-forty-three-characters-long';

    protected Client $client;

    protected User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);

        $portal = Application::factory()->create(['name' => 'portal']);
        $maestros = Application::factory()->create(['name' => 'api-maestros']);

        OAuthScope::factory()->for($portal)->create(['name' => 'portal:ventas.crear']);
        OAuthScope::factory()->for($maestros)->create(['name' => 'geografia:leer']);

        $coordinador = Role::factory()->for($portal)->withScopes(['portal:ventas.crear'])->create(['name' => 'coordinador']);
        $lector = Role::factory()->for($maestros)->withScopes(['geografia:leer'])->create(['name' => 'lector']);

        $this->ana = User::factory()->withRoles($coordinador)->create(['email' => 'ana@example.com', 'password' => 'secret-password']);
        User::factory()->withRoles($lector)->create(['email' => 'beto@example.com', 'password' => 'secret-password']);

        $this->client = $this->createClient('portal-web', 'authorization-code', 'portal', [self::REDIRECT_URI]);
    }

    protected function challenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', self::VERIFIER, true)), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, string|null>  $overrides
     */
    protected function authorizeUrl(array $overrides = []): string
    {
        return '/api/auth/oauth/authorize?'.http_build_query(array_filter([
            'response_type' => 'code',
            'client_id' => $this->client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz-state',
            'code_challenge' => $this->challenge(),
            'code_challenge_method' => 'S256',
            ...$overrides,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, string>
     */
    protected function queryOf(TestResponse $response): array
    {
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * Authorize as a person with a session and exchange the code for tokens.
     *
     * @return array<string, mixed>
     */
    protected function tokensFor(User $user): array
    {
        $response = $this->actingAs($user)->get($this->authorizeUrl());

        return $this->exchange($this->queryOf($response)['code'])->assertOk()->json();
    }

    protected function exchange(string $code, string $verifier = self::VERIFIER): TestResponse
    {
        return $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client->getKey(),
            'client_secret' => $this->client->plainSecret,
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => $verifier,
            'code' => $code,
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

    public function test_the_login_form_is_shown(): void
    {
        $this->get('/api/auth/login')
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_a_person_with_a_session_sees_it_instead_of_the_form(): void
    {
        $this->actingAs($this->ana)->get('/api/auth/login')
            ->assertOk()
            ->assertSee('Ya iniciaste sesión')
            ->assertSee('ana@example.com')
            ->assertSee('Cerrar sesión')
            ->assertDontSee('name="password"', false);
    }

    public function test_a_guest_sent_to_the_login_returns_to_the_authorization_request(): void
    {
        $this->get($this->authorizeUrl())->assertRedirect(route('login'));

        $login = $this->post('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'secret-password']);

        $this->assertAuthenticatedAs($this->ana);
        $login->assertRedirectContains('/api/auth/oauth/authorize?');

        $code = $this->get($login->headers->get('Location'));
        $code->assertRedirectContains(self::REDIRECT_URI.'?');
        $this->assertSame('xyz-state', $this->queryOf($code)['state']);
        $this->assertNotEmpty($this->queryOf($code)['code']);
    }

    public function test_the_session_cookie_is_scoped_to_the_api_and_hidden_from_javascript(): void
    {
        $cookie = $this->post('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'secret-password'])
            ->getCookie(config('session.cookie'), false);

        $this->assertSame('/api/auth', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_wrong_credentials_go_back_to_the_form_without_a_session(): void
    {
        $this->from('/api/auth/login')
            ->post('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'wrong-password'])
            ->assertRedirect('/api/auth/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_invalid_input_redirects_back_with_errors_instead_of_a_problem(): void
    {
        $this->from('/api/auth/login')
            ->post('/api/auth/login', ['email' => 'not-an-email'])
            ->assertRedirect('/api/auth/login')
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_the_web_login_is_limited_to_ten_attempts_a_minute(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->post('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'wrong-password']);
        }

        $this->post('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'wrong-password'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_authorization_requires_a_pkce_challenge_before_asking_for_credentials(): void
    {
        $this->get($this->authorizeUrl(['code_challenge' => null, 'code_challenge_method' => null]))
            ->assertStatus(400)
            ->assertHeaderMissing('Location');

        $this->assertDatabaseCount('oauth_auth_codes', 0);
    }

    public function test_authorization_rejects_the_plain_challenge_method(): void
    {
        $this->get($this->authorizeUrl(['code_challenge_method' => 'plain']))->assertStatus(400);
        $this->actingAs($this->ana)->get($this->authorizeUrl(['code_challenge_method' => 'plain']))->assertStatus(400);
    }

    public function test_a_person_without_roles_in_the_client_application_gets_no_code(): void
    {
        $this->actingAs(User::firstWhere('email', 'beto@example.com'))->get($this->authorizeUrl())
            ->assertForbidden()
            ->assertSee('No tienes acceso a este sistema, contacta a soporte')
            ->assertHeaderMissing('Location');

        $this->assertDatabaseCount('oauth_auth_codes', 0);
    }

    public function test_a_person_with_roles_in_the_client_application_gets_a_code_without_a_consent_screen(): void
    {
        $response = $this->actingAs($this->ana)->get($this->authorizeUrl());

        $response->assertRedirectContains(self::REDIRECT_URI.'?');
        $this->assertSame('xyz-state', $this->queryOf($response)['state']);
        $this->assertDatabaseCount('oauth_auth_codes', 1);
    }

    public function test_a_redirect_uri_that_is_not_registered_is_not_followed(): void
    {
        $response = $this->actingAs($this->ana)->get($this->authorizeUrl(['redirect_uri' => 'https://evil.example.com/callback']));

        $response->assertStatus(401)->assertJsonPath('error', 'invalid_client');
        $this->assertDatabaseCount('oauth_auth_codes', 0);
    }

    public function test_an_unknown_client_is_rejected(): void
    {
        $this->actingAs($this->ana)->get($this->authorizeUrl(['client_id' => 'not-a-client']))->assertStatus(401);
        $this->actingAs($this->ana)->get($this->authorizeUrl(['client_id' => fake()->uuid()]))->assertStatus(401);
    }

    public function test_consent_is_not_supported(): void
    {
        $this->actingAs($this->ana)->get($this->authorizeUrl(['prompt' => 'consent']))
            ->assertStatus(400)
            ->assertHeaderMissing('Location');
    }

    public function test_prompt_login_closes_the_session_and_asks_for_the_credentials_again(): void
    {
        $this->actingAs($this->ana)->get($this->authorizeUrl(['prompt' => 'login']))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $login = $this->post('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'secret-password']);

        $this->get($login->headers->get('Location'))->assertRedirectContains(self::REDIRECT_URI.'?');
    }

    public function test_prompt_login_checks_the_roles_of_whoever_logs_in(): void
    {
        $this->actingAs($this->ana)->get($this->authorizeUrl(['prompt' => 'login']))->assertRedirect(route('login'));

        $login = $this->post('/api/auth/login', ['email' => 'beto@example.com', 'password' => 'secret-password']);

        $this->get($login->headers->get('Location'))->assertForbidden();
    }

    public function test_the_code_is_exchanged_for_tokens_with_the_roles_and_scopes_of_the_application(): void
    {
        $tokens = $this->tokensFor($this->ana);

        $this->assertNotEmpty($tokens['refresh_token']);

        $claims = $this->claimsOf($tokens['access_token']);

        $this->assertSame(['coordinador'], $claims['roles']);
        $this->assertSame(['portal:ventas.crear'], $claims['scopes']);
        $this->assertSame($this->ana->getKey(), (int) $claims['sub']);
    }

    public function test_a_wrong_code_verifier_is_rejected(): void
    {
        $code = $this->queryOf($this->actingAs($this->ana)->get($this->authorizeUrl()))['code'];

        $this->exchange($code, 'another-verifier-of-at-least-forty-three-characters')
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $code = $this->queryOf($this->actingAs($this->ana)->get($this->authorizeUrl()))['code'];

        $this->exchange($code)->assertOk();
        $this->exchange($code)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_logout_with_a_valid_return_closes_the_session_and_revokes_the_tokens(): void
    {
        $tokens = $this->tokensFor($this->ana);

        $this->get('/api/auth/logout?'.http_build_query([
            'client_id' => $this->client->getKey(),
            'return' => 'https://portal.ubo.cl/cerrada?x=1',
        ]))->assertRedirect('https://portal.ubo.cl/cerrada?x=1');

        $this->assertGuest();
        $this->assertSame(0, DB::table('oauth_access_tokens')->where('revoked', false)->count());

        $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->client->getKey(),
            'client_secret' => $this->client->plainSecret,
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_logout_does_not_return_to_an_origin_the_client_has_not_registered(): void
    {
        $this->tokensFor($this->ana);

        $this->get('/api/auth/logout?'.http_build_query([
            'client_id' => $this->client->getKey(),
            'return' => 'https://evil.example.com/portal',
        ]))->assertOk()->assertSee('Sesión cerrada')->assertHeaderMissing('Location');

        $this->assertGuest();
        $this->assertSame(0, DB::table('oauth_access_tokens')->where('revoked', false)->count());
    }

    public function test_logout_without_parameters_shows_that_the_session_is_closed(): void
    {
        $this->actingAs($this->ana)->get('/api/auth/logout')->assertOk()->assertSee('Sesión cerrada');

        $this->assertGuest();
    }

    public function test_logout_from_the_iam_page_closes_the_session_and_shows_the_login(): void
    {
        $this->tokensFor($this->ana);

        $this->post('/api/auth/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, DB::table('oauth_access_tokens')->where('revoked', false)->count());
    }
}
