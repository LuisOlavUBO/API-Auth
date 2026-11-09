<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as MicrosoftAccount;
use Tests\TestCase;

/**
 * The login with Microsoft Entra ID. Microsoft is faked: Socialite returns the account.
 */
class MicrosoftLoginTest extends TestCase
{
    use RefreshDatabase;

    protected const TENANT = '11111111-2222-3333-4444-555555555555';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.azure' => [
            'client_id' => 'entra-client-id',
            'client_secret' => 'entra-secret',
            'redirect' => 'https://iam.ubo.cl/api/auth/login/microsoft/callback',
            'tenant' => self::TENANT,
        ]]);
    }

    /**
     * Make Microsoft answer the callback with this account.
     */
    protected function fakeAccount(string $oid, ?string $mail, string $principalName = 'upn@ubo.cl', string $name = 'Ana Pérez'): void
    {
        $account = (new MicrosoftAccount)
            ->setRaw(['id' => $oid, 'mail' => $mail, 'userPrincipalName' => $principalName])
            ->map(['id' => $oid, 'name' => $name, 'email' => $principalName, 'mail' => $mail]);

        Socialite::shouldReceive('driver->user')->andReturn($account);
    }

    public function test_the_login_shows_the_microsoft_button_when_entra_is_configured(): void
    {
        $this->get('/api/auth/login')
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('Ingresar con Microsoft');
    }

    public function test_without_configuration_there_is_no_button_and_the_routes_are_not_found(): void
    {
        config(['services.azure.client_id' => null]);

        $this->get('/api/auth/login')->assertOk()->assertDontSee('Ingresar con Microsoft');
        $this->get('/api/auth/login/microsoft')->assertNotFound();
        $this->get('/api/auth/login/microsoft/callback')->assertNotFound();
    }

    public function test_the_person_is_sent_to_the_sign_in_page_of_the_ubo_tenant(): void
    {
        $this->get('/api/auth/login/microsoft')
            ->assertRedirectContains('https://login.microsoftonline.com/'.self::TENANT.'/oauth2/v2.0/authorize?');
    }

    public function test_an_error_from_microsoft_goes_back_to_the_login_without_a_session(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

        $this->get('/api/auth/login/microsoft/callback?error=access_denied')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['microsoft' => 'No pudimos iniciar sesión con Microsoft, intenta de nuevo.']);

        $this->assertGuest();
    }

    public function test_a_linked_user_is_found_by_oid_even_if_the_email_changed(): void
    {
        $oid = (string) Str::uuid();
        $ana = User::factory()->create(['email' => 'ana@ubo.cl', 'entra_oid' => $oid]);
        $this->fakeAccount($oid, 'ana.perez@ubo.cl');

        $this->get('/api/auth/login/microsoft/callback')->assertRedirect(route('login'));

        $this->assertAuthenticatedAs($ana);
        $this->assertSame('ana@ubo.cl', $ana->fresh()->email);
    }

    public function test_the_first_time_the_user_with_the_same_email_is_linked_and_keeps_the_password(): void
    {
        $oid = (string) Str::uuid();
        $ana = User::factory()->create(['email' => 'ana@ubo.cl', 'password' => 'secret-password']);
        $this->fakeAccount($oid, 'ana@ubo.cl');

        $this->get('/api/auth/login/microsoft/callback');

        $this->assertAuthenticatedAs($ana);
        $this->assertSame(1, User::where('entra_oid', $oid)->whereKey($ana->getKey())->count());
        $this->assertTrue(Auth()->validate(['email' => 'ana@ubo.cl', 'password' => 'secret-password']));
    }

    public function test_the_principal_name_is_used_when_the_account_has_no_mail(): void
    {
        $ana = User::factory()->create(['email' => 'ana@ubo.cl']);
        $this->fakeAccount((string) Str::uuid(), null, 'ana@ubo.cl');

        $this->get('/api/auth/login/microsoft/callback');

        $this->assertAuthenticatedAs($ana);
    }

    public function test_an_email_linked_to_another_account_asks_to_contact_support(): void
    {
        User::factory()->create(['email' => 'ana@ubo.cl', 'entra_oid' => (string) Str::uuid()]);
        $this->fakeAccount((string) Str::uuid(), 'ana@ubo.cl');

        $this->get('/api/auth/login/microsoft/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['microsoft' => 'No pudimos asociar tu cuenta de Microsoft, contacta a soporte.']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_new_person_gets_a_user_without_password_nor_roles(): void
    {
        $oid = (string) Str::uuid();
        $this->fakeAccount($oid, 'nuevo@ubo.cl', name: 'Nuevo Funcionario');

        $this->get('/api/auth/login/microsoft/callback');

        $user = User::firstWhere('email', 'nuevo@ubo.cl');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Nuevo Funcionario', $user->name);
        $this->assertNull($user->password);
        $this->assertSame(0, $user->roles()->count());
        $this->assertSame(1, User::where('entra_oid', $oid)->count());
    }

    public function test_two_users_cannot_share_an_oid_but_many_can_have_none(): void
    {
        User::factory()->count(2)->create();
        $oid = (string) Str::uuid();
        User::factory()->create(['entra_oid' => $oid]);

        $this->expectException(QueryException::class);
        User::factory()->create(['entra_oid' => $oid]);
    }

    /**
     * A guest opens the authorization of a "portal" client and logs in with Microsoft.
     * Returns the response to the authorization request they are sent back to.
     */
    protected function authorizeWithMicrosoft(): TestResponse
    {
        $client = $this->createClient('portal-web', 'authorization-code', 'portal', [self::REDIRECT_URI]);

        $this->get('/api/auth/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz-state',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]))->assertRedirect(route('login'));

        $callback = $this->get('/api/auth/login/microsoft/callback');
        $callback->assertRedirectContains('/api/auth/oauth/authorize?');

        return $this->get($callback->headers->get('Location'));
    }

    public function test_a_person_with_roles_logs_in_with_microsoft_and_returns_to_the_satellite_with_a_code(): void
    {
        $coordinador = Role::factory()->for(Application::factory()->create(['name' => 'portal']))->create(['name' => 'coordinador']);
        $oid = (string) Str::uuid();
        User::factory()->withRoles($coordinador)->create(['email' => 'ana@ubo.cl', 'entra_oid' => $oid]);
        $this->fakeAccount($oid, 'ana@ubo.cl');

        $this->authorizeWithMicrosoft()
            ->assertRedirectContains(self::REDIRECT_URI.'?')
            ->assertRedirectContains('state=xyz-state');
    }

    public function test_a_new_person_from_microsoft_has_no_access_to_the_satellite_yet(): void
    {
        Application::factory()->create(['name' => 'portal']);
        $this->fakeAccount((string) Str::uuid(), 'nuevo@ubo.cl');

        $this->authorizeWithMicrosoft()
            ->assertForbidden()
            ->assertSee('No tienes acceso a este sistema, contacta a soporte');
    }
}
