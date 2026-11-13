<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuthenticationEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as MicrosoftAccount;
use RuntimeException;
use Tests\TestCase;

/**
 * The history of logins, failed attempts, blocks and rejected clients (authentication-events).
 */
class AuthenticationEventsTest extends TestCase
{
    use RefreshDatabase;

    protected const OID_ANA = 'aaaaaaaa-1111-2222-3333-444444444444';

    protected const OID_OTHER = 'bbbbbbbb-1111-2222-3333-444444444444';

    protected User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $portal = Application::factory()->create(['name' => 'portal']);
        $coordinador = Role::factory()->for($portal)->create(['name' => 'coordinador']);

        $this->ana = User::factory()->withRoles($coordinador)->create(['email' => 'ana@example.com', 'password' => 'secret-password']);
    }

    protected function login(string $email, string $password): TestResponse
    {
        return $this->post('/api/auth/login', ['email' => $email, 'password' => $password], ['User-Agent' => 'Navegador de prueba']);
    }

    /**
     * Make Microsoft answer the callback with this account.
     */
    protected function microsoftCallback(string $oid, string $mail): TestResponse
    {
        config(['services.azure' => [
            'client_id' => 'entra-client-id',
            'client_secret' => 'entra-secret',
            'redirect' => 'https://iam.ubo.cl/api/auth/login/microsoft/callback',
            'tenant' => '11111111-2222-3333-4444-555555555555',
        ]]);

        $account = (new MicrosoftAccount)
            ->setRaw(['id' => $oid, 'mail' => $mail])
            ->map(['id' => $oid, 'name' => 'Ana Pérez', 'email' => $mail, 'mail' => $mail]);

        Socialite::shouldReceive('driver->user')->andReturn($account);

        return $this->get('/api/auth/login/microsoft/callback');
    }

    protected function clientToken(Client $client, string $secret): TestResponse
    {
        return $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $secret,
        ]);
    }

    public function test_a_password_login_is_recorded(): void
    {
        $client = $this->createCodeClient('portal');
        $this->get($this->authorizeUrl($client));

        $this->login('ana@example.com', 'secret-password');

        $this->assertAuthenticatedAs($this->ana);
        $event = AuthenticationEvent::sole();
        $this->assertSame('login_ok', $event->type);
        $this->assertSame('clave', $event->method);
        $this->assertEquals($this->ana->id, $event->user_id);
        $this->assertSame('127.0.0.1', $event->ip_address);
        $this->assertSame('Navegador de prueba', $event->user_agent);
        $this->assertSame($client->getKey(), $event->client_id);
        $this->assertNull($event->email);
    }

    public function test_a_microsoft_login_is_recorded(): void
    {
        $this->microsoftCallback(self::OID_ANA, 'ana@example.com');

        $this->assertAuthenticatedAs($this->ana);
        $event = AuthenticationEvent::sole();
        $this->assertSame('login_ok', $event->type);
        $this->assertSame('microsoft', $event->method);
        $this->assertEquals($this->ana->id, $event->user_id);
    }

    public function test_a_wrong_password_is_recorded_without_the_password(): void
    {
        $this->login('Ana@Example.com', 'wrong-password');

        $this->assertGuest();
        $event = AuthenticationEvent::sole();
        $this->assertSame('login_fallido', $event->type);
        $this->assertSame('credenciales', $event->reason);
        $this->assertEquals($this->ana->id, $event->user_id);
        $this->assertSame('ana@example.com', $event->email);
        $this->assertSame('127.0.0.1', $event->ip_address);
        $this->assertStringNotContainsString('wrong-password', json_encode($event->getAttributes()));
    }

    public function test_an_unknown_email_is_recorded_without_a_user(): void
    {
        $this->login('nadie@example.com', 'secret-password');

        $event = AuthenticationEvent::sole();
        $this->assertSame('credenciales', $event->reason);
        $this->assertNull($event->user_id);
        $this->assertSame('nadie@example.com', $event->email);
    }

    public function test_a_disabled_user_and_an_expired_temporary_password_are_recorded_with_their_reason(): void
    {
        $this->ana->forceFill(['disabled_at' => now()])->save();
        $this->login('ana@example.com', 'secret-password');

        $this->ana->forceFill(['disabled_at' => null, 'temporary_password_expires_at' => now()->subMinute()])->save();
        $this->login('ana@example.com', 'secret-password');

        $this->assertSame(['baja', 'clave_vencida'], AuthenticationEvent::orderBy('id')->pluck('reason')->all());
        $this->assertSame(['login_fallido'], AuthenticationEvent::distinct()->pluck('type')->all());
    }

    public function test_a_microsoft_account_whose_email_belongs_to_another_linked_account_is_recorded(): void
    {
        $this->ana->forceFill(['entra_oid' => self::OID_ANA])->save();

        $this->microsoftCallback(self::OID_OTHER, 'ana@example.com');

        $this->assertGuest();
        $event = AuthenticationEvent::sole();
        $this->assertSame('login_fallido', $event->type);
        $this->assertSame('microsoft', $event->method);
        $this->assertSame('cuenta_vinculada_a_otra', $event->reason);
        $this->assertEquals($this->ana->id, $event->user_id);
        $this->assertNull($event->email);
    }

    public function test_the_attempt_rejected_by_the_rate_limit_is_recorded(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->login('ana@example.com', 'wrong-password');
        }

        $this->login('ana@example.com', 'wrong-password')->assertTooManyRequests()->assertHeader('Retry-After');

        $event = AuthenticationEvent::where('type', 'login_bloqueado')->sole();
        $this->assertSame('ana@example.com', $event->email);
        $this->assertSame('127.0.0.1', $event->ip_address);
        $this->assertSame(10, AuthenticationEvent::where('type', 'login_fallido')->count());
    }

    public function test_a_client_with_an_old_secret_is_recorded_without_the_secret(): void
    {
        $client = $this->createClient('sistema');

        $this->clientToken($client, 'secreto-anterior')->assertUnauthorized()->assertJsonPath('error', 'invalid_client');

        $event = AuthenticationEvent::sole();
        $this->assertSame('cliente_rechazado', $event->type);
        $this->assertSame('client_credentials', $event->method);
        $this->assertSame($client->getKey(), $event->client_id);
        $this->assertSame('secreto_invalido', $event->reason);
        $this->assertSame('127.0.0.1', $event->ip_address);
        $this->assertStringNotContainsString('secreto-anterior', json_encode($event->getAttributes()));
    }

    public function test_a_revoked_or_unknown_client_is_recorded_with_its_reason(): void
    {
        $client = $this->createClient('sistema');
        $client->forceFill(['revoked' => true])->save();
        Once::flush();
        $unknownId = (string) Str::uuid();

        $this->clientToken($client, $client->plainSecret)->assertUnauthorized();
        $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $unknownId,
            'client_secret' => 'cualquiera',
        ])->assertUnauthorized();

        $this->assertSame(
            [[$client->getKey(), 'cliente_revocado'], [$unknownId, 'cliente_inexistente']],
            AuthenticationEvent::orderBy('id')->get()->map(fn (AuthenticationEvent $event) => [$event->client_id, $event->reason])->all(),
        );
    }

    public function test_a_client_that_gets_its_token_is_not_recorded(): void
    {
        $client = $this->createClient('sistema');

        $this->clientToken($client, $client->plainSecret)->assertOk();

        $this->assertDatabaseCount('authentication_events', 0);
    }

    public function test_a_failure_to_record_does_not_stop_the_login(): void
    {
        Log::spy();
        AuthenticationEvent::creating(fn () => throw new RuntimeException('Tabla no disponible'));

        $this->login('ana@example.com', 'secret-password')->assertRedirect(route('login'));

        $this->assertAuthenticatedAs($this->ana);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_the_daily_purge_keeps_only_the_last_180_days(): void
    {
        $old = AuthenticationEvent::create(['occurred_at' => now()->subDays(200), 'type' => 'login_ok', 'method' => 'clave']);
        $recent = AuthenticationEvent::create(['occurred_at' => now()->subDays(10), 'type' => 'login_ok', 'method' => 'clave']);

        $this->artisan('model:prune', ['--model' => [AuthenticationEvent::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
