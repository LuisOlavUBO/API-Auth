<?php

namespace Tests\Feature;

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

    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->createClient('Test Password Client', 'password');

        config([
            'oauth.password_client.id' => $client->getKey(),
            'oauth.password_client.secret' => $client->plainSecret,
        ]);

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
     * @return array<string, mixed>
     */
    protected function login(?string $scope = null): array
    {
        return $this->postJson('/api/auth/v1/login', array_filter([
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'scope' => $scope,
        ]))->assertOk()->json();
    }

    /**
     * Read the "scopes" claim straight from the JWT payload.
     *
     * @return string[]
     */
    protected function scopesOf(string $accessToken): array
    {
        $payload = explode('.', $accessToken)[1];

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true)['scopes'];
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

    public function test_user_can_login_and_fetch_profile(): void
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

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $this->createUser();

        $this->postJson('/api/auth/v1/login', [
            'email' => 'ana@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('error', 'invalid_grant');
    }

    public function test_a_user_without_password_cannot_login_with_one(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => null]);

        $this->postJson('/api/auth/v1/login', [
            'email' => 'ana@example.com',
            'password' => 'anything',
        ])->assertUnauthorized()->assertJsonPath('error', 'invalid_grant');
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/auth/v1/me')->assertUnauthorized();
    }

    public function test_refresh_token_issues_a_new_pair(): void
    {
        $this->createUser();
        $first = $this->login();

        $second = $this->postJson('/api/auth/v1/refresh', ['refresh_token' => $first['refresh_token']])
            ->assertOk()
            ->json();

        $this->assertNotSame($first['access_token'], $second['access_token']);

        // The old refresh token is single-use.
        $this->postJson('/api/auth/v1/refresh', ['refresh_token' => $first['refresh_token']])
            ->assertUnauthorized();
    }

    public function test_logout_revokes_access_and_refresh_tokens(): void
    {
        $this->createUser();
        $token = $this->login();

        $this->withToken($token['access_token'])->postJson('/api/auth/v1/logout')->assertOk();

        $this->assertDatabaseMissing('oauth_access_tokens', ['revoked' => false]);
        $this->assertDatabaseMissing('oauth_refresh_tokens', ['revoked' => false]);

        $this->postJson('/api/auth/v1/refresh', ['refresh_token' => $token['refresh_token']])
            ->assertUnauthorized();
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

    public function test_login_without_scope_receives_the_default_scope(): void
    {
        $this->createUser();

        $this->assertSame(['user:read'], $this->scopesOf($this->login()['access_token']));
    }

    public function test_login_can_request_scopes_granted_to_the_role(): void
    {
        $this->createUser();

        $token = $this->login('user:read user:write');

        $this->assertSame(['user:read', 'user:write'], $this->scopesOf($token['access_token']));
    }

    public function test_login_rejects_scopes_outside_the_role(): void
    {
        $this->createUser();

        foreach (['introspect', '*', 'does-not-exist'] as $scope) {
            $this->postJson('/api/auth/v1/login', [
                'email' => 'ana@example.com',
                'password' => 'secret-password',
                'scope' => $scope,
            ])->assertBadRequest()->assertJsonPath('error', 'invalid_scope');
        }
    }

    public function test_users_without_a_role_get_tokens_without_scopes(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret-password']);

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

        $refreshed = $this->postJson('/api/auth/v1/refresh', ['refresh_token' => $token['refresh_token']])
            ->assertOk()
            ->json();

        $this->assertSame(['user:read'], $this->scopesOf($refreshed['access_token']));
    }
}
