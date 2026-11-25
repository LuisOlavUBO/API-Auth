<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleScopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OpenIdConnectTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;

    protected User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleScopeSeeder::class);

        $this->client = $this->createCodeClient();
        $this->ana = User::factory()->withPersona(1029)->withRoles(Role::firstWhere('name', 'user'))
            ->create(['name' => 'Ana', 'email' => 'ana@example.com', 'rut' => '12345678-5']);
    }

    /**
     * @return string[]
     */
    protected function scopesOf(array $tokens): array
    {
        $scopes = $this->claimsOf($tokens['access_token'])['scopes'];
        sort($scopes);

        return $scopes;
    }

    public function test_openid_is_granted_with_the_requested_permissions_only(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'openid user:read');

        $this->assertSame(['openid', 'user:read'], $this->scopesOf($tokens));
        $this->assertSame([$this->client->getKey(), 'api-auth'], $this->claimsOf($tokens['access_token'])['aud']);
    }

    public function test_only_openid_scopes_get_every_permission_of_the_roles(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'openid email');

        $this->assertSame(['email', 'openid', 'user:read', 'user:write'], $this->scopesOf($tokens));
    }

    public function test_a_refresh_keeps_the_openid_scopes(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'openid profile user:read');

        $refreshed = $this->refreshTokens($this->client, $tokens['refresh_token'])->assertOk()->json();

        $this->assertSame(['openid', 'profile', 'user:read'], $this->scopesOf($refreshed));
    }

    public function test_a_client_credentials_token_cannot_ask_for_openid(): void
    {
        $service = $this->createClient('Orders Service');

        $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $service->getKey(),
            'client_secret' => $service->plainSecret,
            'scope' => 'openid',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_scope');
    }

    protected function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * RSA components of a PEM public key, in base64url.
     *
     * @return array{n: string, e: string}
     */
    protected function componentsOf(string $pem): array
    {
        $rsa = openssl_pkey_get_details(openssl_pkey_get_public($pem))['rsa'];

        return ['n' => $this->base64Url($rsa['n']), 'e' => $this->base64Url($rsa['e'])];
    }

    /**
     * Header and claims of an id_token whose signature is valid with the key of the JWKS.
     *
     * @return array{header: array<string, mixed>, claims: array<string, mixed>}
     */
    protected function verifiedIdToken(string $idToken): array
    {
        $jwk = $this->getJson('/api/auth/.well-known/jwks.json')->json('keys.0');
        $pem = $this->get('/api/auth/v1/public-key')->getContent();
        [$header, $payload, $signature] = explode('.', $idToken);

        $this->assertSame(1, openssl_verify("{$header}.{$payload}", base64_decode(strtr($signature, '-_', '+/')), $pem, OPENSSL_ALGO_SHA256));

        $header = json_decode(base64_decode(strtr($header, '-_', '+/')), true);
        $this->assertSame($jwk['kid'], $header['kid']);
        $this->assertSame('RS256', $header['alg']);

        return ['header' => $header, 'claims' => json_decode(base64_decode(strtr($payload, '-_', '+/')), true)];
    }

    /**
     * Authorize as Ana with the given extra parameters and exchange the code.
     *
     * @param  array<string, string|null>  $parameters
     * @return array<string, mixed>
     */
    protected function exchangeWith(array $parameters): array
    {
        $response = $this->actingAs($this->ana)->get($this->authorizeUrl($this->client, $parameters));

        return $this->exchange($this->client, $this->queryOf($response)['code'])->assertOk()->json();
    }

    public function test_exchanging_a_code_with_openid_returns_a_signed_id_token(): void
    {
        config(['app.url' => 'https://apidev.ubo.cl']);

        $tokens = $this->tokensFor($this->ana, $this->client, 'openid');
        $claims = $this->verifiedIdToken($tokens['id_token'])['claims'];

        $this->assertSame('https://apidev.ubo.cl/api/auth', $claims['iss']);
        $this->assertSame($this->ana->uuid, $claims['sub']);
        $this->assertSame(strtolower($this->client->getKey()), $claims['aud']);
        $this->assertSame((int) $this->claimsOf($tokens['access_token'])['exp'], $claims['exp']);
        $this->assertIsInt($claims['iat']);
        $this->assertIsInt($claims['exp']);
    }

    public function test_a_code_without_openid_returns_no_id_token(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'user:read');

        $this->assertArrayNotHasKey('id_token', $tokens);
    }

    public function test_a_client_credentials_token_has_no_id_token(): void
    {
        $service = $this->createClient('Orders Service');
        Role::firstWhere('name', 'introspector')->clients()->attach($service->getKey());

        $response = $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $service->getKey(),
            'client_secret' => $service->plainSecret,
            'scope' => 'introspect',
        ])->assertOk();

        $this->assertArrayNotHasKey('id_token', $response->json());
    }

    public function test_the_id_token_carries_the_person_as_text_and_never_the_rut(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'openid');
        $claims = $this->verifiedIdToken($tokens['id_token'])['claims'];

        $this->assertSame('1029', $claims['id_persona']);
        $this->assertStringNotContainsString('12345678', json_encode($claims));
        $this->assertArrayNotHasKey('name', $claims);
        $this->assertArrayNotHasKey('email', $claims);
    }

    public function test_the_id_token_of_a_user_without_a_person_has_no_id_persona(): void
    {
        $this->ana->update(['id_persona' => null]);

        $tokens = $this->tokensFor($this->ana, $this->client, 'openid');

        $this->assertArrayNotHasKey('id_persona', $this->verifiedIdToken($tokens['id_token'])['claims']);
    }

    public function test_profile_and_email_add_name_and_email_to_the_id_token(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'openid profile email');
        $claims = $this->verifiedIdToken($tokens['id_token'])['claims'];

        $this->assertSame('Ana', $claims['name']);
        $this->assertSame('ana@example.com', $claims['email']);
        $this->assertTrue($claims['email_verified']);
    }

    public function test_a_refresh_returns_a_new_id_token_without_nonce(): void
    {
        $tokens = $this->exchangeWith(['scope' => 'openid', 'nonce' => 'n-0S6_WzA2Mj']);

        $refreshed = $this->refreshTokens($this->client, $tokens['refresh_token'])->assertOk()->json();
        $claims = $this->verifiedIdToken($refreshed['id_token'])['claims'];

        $this->assertSame($this->ana->uuid, $claims['sub']);
        $this->assertArrayNotHasKey('nonce', $claims);
    }

    public function test_the_nonce_of_the_authorization_comes_back_in_the_id_token(): void
    {
        $tokens = $this->exchangeWith(['scope' => 'openid', 'nonce' => 'n-0S6_WzA2Mj']);

        $this->assertSame('n-0S6_WzA2Mj', $this->verifiedIdToken($tokens['id_token'])['claims']['nonce']);
    }

    public function test_an_authorization_without_nonce_gives_an_id_token_without_nonce(): void
    {
        $this->exchangeWith(['scope' => 'openid', 'nonce' => 'first-nonce']);

        $tokens = $this->exchangeWith(['scope' => 'openid']);

        $this->assertArrayNotHasKey('nonce', $this->verifiedIdToken($tokens['id_token'])['claims']);
    }

    public function test_the_client_can_authenticate_with_basic_or_in_the_body(): void
    {
        $response = $this->actingAs($this->ana)->get($this->authorizeUrl($this->client, ['scope' => 'openid']));

        $this->withHeader('Authorization', 'Basic '.base64_encode($this->client->getKey().':'.$this->client->plainSecret))
            ->postJson('/api/auth/oauth/token', [
                'grant_type' => 'authorization_code',
                'redirect_uri' => self::REDIRECT_URI,
                'code_verifier' => self::VERIFIER,
                'code' => $this->queryOf($response)['code'],
            ])->assertOk()->assertJsonStructure(['access_token', 'id_token']);

        $this->flushHeaders();

        $this->assertArrayHasKey('id_token', $this->exchangeWith(['scope' => 'openid']));
    }

    public function test_the_client_can_authenticate_with_form_urlencoded_basic_credentials(): void
    {
        $response = $this->actingAs($this->ana)->get($this->authorizeUrl($this->client, ['scope' => 'openid']));

        // As openid-client sends them (RFC 6749 §2.3.1): the hyphens of the id as %2D, and here every byte of the secret.
        $clientId = str_replace('-', '%2D', $this->client->getKey());
        $clientSecret = implode('', array_map(fn (string $byte): string => '%'.strtoupper(bin2hex($byte)), str_split($this->client->plainSecret)));

        $this->withHeader('Authorization', 'Basic '.base64_encode($clientId.':'.$clientSecret))
            ->postJson('/api/auth/oauth/token', [
                'grant_type' => 'authorization_code',
                'redirect_uri' => self::REDIRECT_URI,
                'code_verifier' => self::VERIFIER,
                'code' => $this->queryOf($response)['code'],
            ])->assertOk()->assertJsonStructure(['access_token', 'id_token']);
    }

    public function test_userinfo_returns_the_claims_of_the_scopes_of_the_token(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'openid email');

        $response = $this->withToken($tokens['access_token'])
            ->getJson('/api/auth/oauth/userinfo')
            ->assertOk()
            ->assertExactJson([
                'sub' => $this->ana->uuid,
                'id_persona' => '1029',
                'email' => 'ana@example.com',
                'email_verified' => true,
            ]);

        $this->assertStringNotContainsString('12345678', $response->getContent());
    }

    public function test_userinfo_requires_a_user_token(): void
    {
        $this->getJson('/api/auth/oauth/userinfo')->assertUnauthorized();

        $service = $this->createClient('Orders Service');
        Role::firstWhere('name', 'introspector')->clients()->attach($service->getKey());
        $serviceToken = $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $service->getKey(),
            'client_secret' => $service->plainSecret,
            'scope' => 'introspect',
        ])->json('access_token');

        $this->withToken($serviceToken)->getJson('/api/auth/oauth/userinfo')->assertUnauthorized();
    }

    public function test_userinfo_requires_openid(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'user:read');

        $this->withToken($tokens['access_token'])->getJson('/api/auth/oauth/userinfo')->assertForbidden();
    }

    /**
     * A JWT RS256 with the given claims, signed with the given private key.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function signedJwt(array $claims, mixed $privateKey): string
    {
        $unsigned = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$this->base64Url(json_encode($claims));
        openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $unsigned.'.'.$this->base64Url($signature);
    }

    public function test_an_openid_logout_returns_to_the_client_with_its_state(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'openid');

        $this->get('/api/auth/logout?'.http_build_query([
            'id_token_hint' => $tokens['id_token'],
            'post_logout_redirect_uri' => 'https://portal.ubo.cl/salida',
            'state' => 'abc',
        ]))->assertRedirect('https://portal.ubo.cl/salida?state=abc');

        $this->assertGuest();
        $this->assertSame(0, DB::table('oauth_access_tokens')->where('revoked', false)->count());
    }

    public function test_an_expired_id_token_hint_signed_by_iam_is_accepted(): void
    {
        $this->tokensFor($this->ana, $this->client, 'openid');
        $hint = $this->signedJwt(['aud' => $this->client->getKey(), 'exp' => time() - 3600], file_get_contents(Passport::keyPath('oauth-private.key')));

        $this->get('/api/auth/logout?'.http_build_query([
            'id_token_hint' => $hint,
            'post_logout_redirect_uri' => 'https://portal.ubo.cl/salida',
        ]))->assertRedirect('https://portal.ubo.cl/salida');
    }

    public function test_an_id_token_hint_signed_by_someone_else_does_not_redirect(): void
    {
        // A real id_token whose payload was changed: its signature is not IAM's for that payload.
        [$header, , $signature] = explode('.', $this->tokensFor($this->ana, $this->client, 'openid')['id_token']);
        $hint = $header.'.'.$this->base64Url(json_encode(['aud' => $this->client->getKey(), 'sub' => 'someone-else'])).'.'.$signature;

        $this->get('/api/auth/logout?'.http_build_query([
            'id_token_hint' => $hint,
            'post_logout_redirect_uri' => 'https://portal.ubo.cl/salida',
        ]))->assertOk()->assertSee('Sesión cerrada')->assertHeaderMissing('Location');

        $this->assertGuest();
        $this->assertSame(0, DB::table('oauth_access_tokens')->where('revoked', false)->count());
    }

    public function test_an_openid_logout_does_not_return_to_an_origin_the_client_has_not_registered(): void
    {
        $tokens = $this->tokensFor($this->ana, $this->client, 'openid');

        $this->get('/api/auth/logout?'.http_build_query([
            'id_token_hint' => $tokens['id_token'],
            'post_logout_redirect_uri' => 'https://evil.example.com/salida',
        ]))->assertOk()->assertSee('Sesión cerrada')->assertHeaderMissing('Location');

        $this->assertGuest();
    }

    public function test_the_discovery_document_is_public_and_points_to_the_issuer(): void
    {
        config(['app.url' => 'https://apidev.ubo.cl']);

        $this->getJson('/api/auth/.well-known/openid-configuration')
            ->assertOk()
            ->assertJsonPath('issuer', 'https://apidev.ubo.cl/api/auth')
            ->assertJsonPath('authorization_endpoint', 'https://apidev.ubo.cl/api/auth/oauth/authorize')
            ->assertJsonPath('token_endpoint', 'https://apidev.ubo.cl/api/auth/oauth/token')
            ->assertJsonPath('userinfo_endpoint', 'https://apidev.ubo.cl/api/auth/oauth/userinfo')
            ->assertJsonPath('jwks_uri', 'https://apidev.ubo.cl/api/auth/.well-known/jwks.json')
            ->assertJsonPath('end_session_endpoint', 'https://apidev.ubo.cl/api/auth/logout');
    }

    public function test_the_discovery_document_lists_what_is_supported(): void
    {
        $this->getJson('/api/auth/.well-known/openid-configuration')
            ->assertOk()
            ->assertJsonPath('response_types_supported', ['code'])
            ->assertJsonPath('scopes_supported', ['openid', 'profile', 'email'])
            ->assertJsonPath('id_token_signing_alg_values_supported', ['RS256'])
            ->assertJsonPath('token_endpoint_auth_methods_supported', ['client_secret_basic', 'client_secret_post'])
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('subject_types_supported', ['public']);
    }

    public function test_the_jwks_publishes_the_same_key_as_public_key_with_its_thumbprint(): void
    {
        $pem = $this->get('/api/auth/v1/public-key')->assertOk()->getContent();
        $components = $this->componentsOf($pem);
        $thumbprint = $this->base64Url(hash('sha256', json_encode(['e' => $components['e'], 'kty' => 'RSA', 'n' => $components['n']]), true));

        $this->getJson('/api/auth/.well-known/jwks.json')
            ->assertOk()
            ->assertJsonCount(1, 'keys')
            ->assertJsonPath('keys.0.kty', 'RSA')
            ->assertJsonPath('keys.0.use', 'sig')
            ->assertJsonPath('keys.0.alg', 'RS256')
            ->assertJsonPath('keys.0.n', $components['n'])
            ->assertJsonPath('keys.0.e', $components['e'])
            ->assertJsonPath('keys.0.kid', $thumbprint);
    }
}
