<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->createClient('Test Password Client', 'password');
        config(['oauth.password_client.id' => $client->getKey(), 'oauth.password_client.secret' => $client->plainSecret]);
        $this->seed(RoleScopeSeeder::class);
        User::factory()->withRoles(Role::firstWhere('name', 'user'))->create([
            'email' => 'ana@example.com',
            'password' => 'secret-password',
        ]);
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    /**
     * Ten failed logins from one IP use up the limit; the eleventh comes from "another" one.
     */
    protected function loginAttemptsThroughProxy(): int
    {
        $proxy = ['REMOTE_ADDR' => '172.18.0.2'];
        $body = ['email' => 'ana@example.com', 'password' => 'wrong-password'];

        foreach (range(1, 10) as $attempt) {
            $this->withServerVariables($proxy)->withHeader('X-Forwarded-For', '10.0.0.5')
                ->postJson('/api/auth/v1/login', $body);
        }

        return $this->withServerVariables($proxy)->withHeader('X-Forwarded-For', '10.0.0.6')
            ->postJson('/api/auth/v1/login', $body)->status();
    }

    public function test_a_trusted_proxy_lets_the_limits_see_the_forwarded_ip(): void
    {
        TrustProxies::at(['172.18.0.2']);

        $this->assertSame(401, $this->loginAttemptsThroughProxy());
    }

    public function test_forwarded_headers_from_an_untrusted_source_are_ignored(): void
    {
        $this->assertSame(429, $this->loginAttemptsThroughProxy());
    }

    public function test_the_session_cookie_is_limited_to_the_api_prefix(): void
    {
        $this->assertSame('/api/auth', config('session.path'));
        $this->assertSame(120, config('session.lifetime'));
    }

    public function test_the_refresh_token_expires_after_120_minutes_by_default(): void
    {
        $this->assertSame(120, config('oauth.refresh_token_ttl'));

        $this->postJson('/api/auth/v1/login', ['email' => 'ana@example.com', 'password' => 'secret-password'])->assertOk();

        $expiresAt = now()->parse(DB::table('oauth_refresh_tokens')->value('expires_at'));
        $this->assertTrue($expiresAt->between(now()->addMinutes(119), now()->addMinutes(121)));
    }

    public function test_the_token_endpoint_is_limited_per_client_not_per_ip(): void
    {
        config(['oauth.token_rate_limit' => 2]);
        $busy = $this->createClient('Busy');
        $quiet = $this->createClient('Quiet');
        $request = fn ($client) => $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
        ]);

        $request($busy)->assertOk();
        $request($busy)->assertOk();
        $request($busy)->assertTooManyRequests()->assertHeader('Retry-After');
        $request($quiet)->assertOk()->assertJsonStructure(['access_token']);
    }
}
