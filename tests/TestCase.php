<?php

namespace Tests;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;

abstract class TestCase extends BaseTestCase
{
    protected const REDIRECT_URI = 'https://portal.ubo.cl/callback';

    protected const VERIFIER = 'a-code-verifier-of-at-least-forty-three-characters-long';

    /**
     * Create an OAuth client of an application (created when missing).
     *
     * @param  'client-credentials'|'authorization-code'  $type
     * @param  string[]  $redirectUris
     */
    protected function createClient(string $name, string $type = 'client-credentials', string $application = 'api-auth', array $redirectUris = []): Client
    {
        return Application::firstOrCreate(['name' => $application])->createClient($name, $type, $redirectUris);
    }

    /**
     * Create a client of the central login that redirects to REDIRECT_URI.
     */
    protected function createCodeClient(string $application = 'api-auth', string $name = 'portal-web'): Client
    {
        return $this->createClient($name, 'authorization-code', $application, [self::REDIRECT_URI]);
    }

    protected function challenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', self::VERIFIER, true)), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, string|null>  $overrides
     */
    protected function authorizeUrl(Client $client, array $overrides = []): string
    {
        return '/api/auth/oauth/authorize?'.http_build_query(array_filter([
            'response_type' => 'code',
            'client_id' => $client->getKey(),
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

    protected function exchange(Client $client, string $code, string $verifier = self::VERIFIER): TestResponse
    {
        return $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => $verifier,
            'code' => $code,
        ]);
    }

    /**
     * Authorize as a person with a session and exchange the code for tokens.
     *
     * @return array<string, mixed>
     */
    protected function tokensFor(User $user, Client $client, ?string $scope = null): array
    {
        $response = $this->actingAs($user)->get($this->authorizeUrl($client, ['scope' => $scope]));

        return $this->exchange($client, $this->queryOf($response)['code'])->assertOk()->json();
    }

    protected function refreshTokens(Client $client, string $refreshToken): TestResponse
    {
        return $this->postJson('/api/auth/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'refresh_token' => $refreshToken,
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
}
