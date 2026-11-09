<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createCodeClient();

        $this->seed(RoleScopeSeeder::class);
    }

    protected function createUser(string $role = 'user'): User
    {
        return User::factory()->withRoles(Role::firstWhere('name', $role))->create([
            'email' => 'ana@example.com',
            'password' => 'secret-password',
        ]);
    }

    /**
     * Tokens of ana through the central login.
     *
     * @return array<string, mixed>
     */
    protected function login(?string $scope = null): array
    {
        return $this->tokensFor(User::firstWhere('email', 'ana@example.com'), $this->client, $scope);
    }

    /**
     * @return string[]
     */
    protected function scopesOf(string $accessToken): array
    {
        return $this->claimsOf($accessToken)['scopes'];
    }

    public function test_people_cannot_register_themselves(): void
    {
        $this->postJson('/api/auth/v1/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_people_cannot_get_tokens_from_the_api_with_their_password(): void
    {
        $this->createUser();
        $credentials = ['email' => 'ana@example.com', 'password' => 'secret-password'];

        $this->postJson('/api/auth/v1/login', $credentials)->assertNotFound();
        $this->postJson('/api/auth/v1/refresh', ['refresh_token' => 'any'])->assertNotFound();

        $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $this->client->getKey(),
            'client_secret' => $this->client->plainSecret,
            'username' => $credentials['email'],
            'password' => $credentials['password'],
        ])->assertJsonPath('error', 'unsupported_grant_type')->assertJsonMissingPath('access_token');

        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_several_users_can_be_saved_without_rut(): void
    {
        User::factory()->count(2)->create(['rut' => null]);

        $this->assertSame(2, User::whereNull('rut')->count());
    }

    public function test_users_get_a_unique_uuid_and_keep_a_numeric_id(): void
    {
        $ana = $this->createUser();
        $beto = User::factory()->create();

        $this->assertTrue(Str::isUuid($ana->fresh()->uuid));
        $this->assertSame($ana->uuid, $ana->fresh()->uuid);
        $this->assertNotEquals($ana->uuid, $beto->uuid);
        $this->assertIsInt($ana->id);
    }

    public function test_user_can_fetch_the_profile(): void
    {
        $user = $this->createUser();
        $user->update(['rut' => '12345678-5']);

        $token = $this->login();

        $this->assertStringNotContainsString('12345678', base64_decode(strtr(explode('.', $token['access_token'])[1], '-_', '+/')));

        $this->withToken($token['access_token'])
            ->getJson('/api/auth/v1/me')
            ->assertOk()
            ->assertJsonPath('email', 'ana@example.com')
            ->assertJsonPath('uuid', $user->fresh()->uuid)
            ->assertJsonPath('rut', '12345678-5')
            ->assertJsonMissingPath('password');
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/auth/v1/me')->assertUnauthorized();
    }

    public function test_refresh_token_issues_a_new_pair(): void
    {
        $this->createUser();
        $first = $this->login();

        $second = $this->refreshTokens($this->client, $first['refresh_token'])->assertOk()->json();

        $this->assertNotSame($first['access_token'], $second['access_token']);
        $this->assertNotSame($first['refresh_token'], $second['refresh_token']);

        // The old refresh token is single-use.
        $this->refreshTokens($this->client, $first['refresh_token'])->assertJsonPath('error', 'invalid_grant');
    }

    public function test_a_refresh_token_does_not_work_with_another_client(): void
    {
        $this->createUser();
        $token = $this->login();

        $this->refreshTokens($this->createCodeClient(name: 'otro-portal'), $token['refresh_token'])
            ->assertJsonMissingPath('access_token')
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_logout_revokes_access_and_refresh_tokens(): void
    {
        $this->createUser();
        $token = $this->login();

        $this->withToken($token['access_token'])->postJson('/api/auth/v1/logout')->assertOk();

        $this->assertDatabaseMissing('oauth_access_tokens', ['revoked' => false]);
        $this->assertDatabaseMissing('oauth_refresh_tokens', ['revoked' => false]);

        $this->refreshTokens($this->client, $token['refresh_token'])->assertJsonMissingPath('access_token');
    }

    public function test_logout_all_revokes_every_session(): void
    {
        $this->createUser();
        $first = $this->login();
        $this->login();

        $this->withToken($first['access_token'])->postJson('/api/auth/v1/logout-all')->assertOk();

        $this->assertDatabaseCount('oauth_access_tokens', 2);
        $this->assertDatabaseMissing('oauth_access_tokens', ['revoked' => false]);
    }

    public function test_a_login_can_request_scopes_granted_to_the_role(): void
    {
        $this->createUser();

        $token = $this->login('user:read user:write');

        $this->assertSame(['user:read', 'user:write'], $this->scopesOf($token['access_token']));
    }

    public function test_a_login_without_scope_gets_every_scope_of_the_role(): void
    {
        $this->createUser();

        $this->assertSame(['user:read', 'user:write'], $this->scopesOf($this->login()['access_token']));
    }

    public function test_a_login_rejects_scopes_outside_the_role(): void
    {
        $this->createUser();

        $response = $this->actingAs(User::first())->get($this->authorizeUrl($this->client, ['scope' => 'introspect']));

        $this->exchange($this->client, $this->queryOf($response)['code'])
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_scope')
            ->assertJsonMissingPath('access_token');
    }

    public function test_roles_without_scopes_give_tokens_without_scopes(): void
    {
        $role = Role::factory()->for($this->client->application)->create(['name' => 'sin-permisos']);
        User::factory()->withRoles($role)->create(['email' => 'ana@example.com', 'password' => 'secret-password']);

        $token = $this->login();

        $this->assertSame([], $this->scopesOf($token['access_token']));

        $this->withToken($token['access_token'])->getJson('/api/auth/v1/me')->assertForbidden();
    }

    public function test_me_requires_the_user_read_scope(): void
    {
        $this->createUser();

        $token = $this->login('user:write');

        $this->withToken($token['access_token'])->getJson('/api/auth/v1/me')->assertForbidden();
    }

    public function test_refresh_drops_scopes_the_role_no_longer_has(): void
    {
        $this->createUser();
        $token = $this->login('user:read user:write');

        Role::firstWhere('name', 'user')->oauthScopes()->detach(
            OAuthScope::firstWhere('name', 'user:write')
        );

        $refreshed = $this->refreshTokens($this->client, $token['refresh_token'])->assertOk()->json();

        $this->assertSame(['user:read'], $this->scopesOf($refreshed['access_token']));
    }
}
