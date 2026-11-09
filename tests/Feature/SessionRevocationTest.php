<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A service (API-IAM) closes every session of a user: tokens and IAM sessions.
 */
class SessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);

        $this->ana = User::factory()->withRoles(Role::firstWhere('name', 'user'))->create(['email' => 'ana@example.com']);
    }

    protected function serviceToken(string $role = 'session-revoker', string $scope = 'sessions:revoke'): string
    {
        $client = $this->createClient('iam-backend');
        Role::firstWhere('name', $role)->clients()->attach($client->getKey());

        return $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => $scope,
        ])->assertOk()->json('access_token');
    }

    protected function revoke(string $token, string $uuid): TestResponse
    {
        // Guards are cached across requests inside one test; reset like a new HTTP request would.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson("/api/auth/v1/users/{$uuid}/revoke");
    }

    public function test_a_service_closes_every_session_of_a_user(): void
    {
        $client = $this->createCodeClient();
        $tokens = $this->tokensFor($this->ana, $client);
        $this->tokensFor($this->ana, $client);
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $this->ana->id,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->revoke($this->serviceToken(), $this->ana->uuid)->assertNoContent();

        $this->assertSame(0, DB::table('oauth_access_tokens')->where('user_id', $this->ana->id)->where('revoked', false)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->ana->id)->count());
        $this->refreshTokens($client, $tokens['refresh_token'])->assertJsonMissingPath('access_token');
    }

    public function test_revoking_a_user_without_sessions_also_answers_204(): void
    {
        $token = $this->serviceToken();

        $this->revoke($token, $this->ana->uuid)->assertNoContent();
        $this->revoke($token, $this->ana->uuid)->assertNoContent();
    }

    public function test_an_unknown_user_is_not_found(): void
    {
        $this->revoke($this->serviceToken(), (string) Str::uuid())->assertNotFound();
    }

    public function test_a_service_token_without_the_scope_is_forbidden(): void
    {
        $this->revoke($this->serviceToken('introspector', 'introspect'), $this->ana->uuid)->assertForbidden();
    }

    public function test_a_user_token_cannot_revoke(): void
    {
        $userToken = $this->tokensFor($this->ana, $this->createCodeClient())['access_token'];

        $this->revoke($userToken, $this->ana->uuid)->assertUnauthorized();
    }
}
