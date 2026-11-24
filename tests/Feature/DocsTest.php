<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DocsTest extends TestCase
{
    public function test_the_root_redirects_to_swagger(): void
    {
        $this->get('/')->assertRedirect('/api/auth/docs/swagger');
        $this->get('/docs/swagger')->assertRedirect('/api/auth/docs/swagger');
    }

    public function test_swagger_ui_loads_the_openapi_spec(): void
    {
        $this->get('/api/auth/docs/swagger')
            ->assertOk()
            ->assertSee('swagger-ui', false)
            ->assertSee('/api/auth/docs/openapi.yaml', false);
    }

    public function test_the_openapi_spec_is_served_as_yaml(): void
    {
        $response = $this->get('/api/auth/docs/openapi.yaml')->assertOk();

        $this->assertStringStartsWith('application/yaml', $response->headers->get('Content-Type'));
        $this->assertSame('3.1.0', Yaml::parse($response->streamedContent())['openapi']);
    }

    public function test_api_docs_false_hides_the_docs_and_their_redirects(): void
    {
        $this->bootWithDocsOff();

        foreach (['/api/auth/docs/swagger', '/api/auth/docs/openapi.yaml'] as $uri) {
            $response = $this->get($uri)->assertNotFound();
            $this->assertStringStartsWith('application/problem+json', $response->headers->get('Content-Type'));
        }

        $this->get('/')->assertNotFound();
        $this->get('/docs/swagger')->assertNotFound();
    }

    public function test_api_docs_false_keeps_the_discovery_and_the_public_key(): void
    {
        $this->bootWithDocsOff();

        $this->get('/api/auth/.well-known/openid-configuration')->assertOk();
        $this->get('/api/auth/.well-known/jwks.json')->assertOk();
        $this->get('/api/auth/v1/public-key')->assertOk();
    }

    /**
     * Routes are registered when the application boots, so it is rebooted with API_DOCS=false.
     */
    private function bootWithDocsOff(): void
    {
        putenv('API_DOCS=false');
        $_ENV['API_DOCS'] = $_SERVER['API_DOCS'] = 'false';

        $this->beforeApplicationDestroyed(function (): void {
            putenv('API_DOCS');
            unset($_ENV['API_DOCS'], $_SERVER['API_DOCS']);
        });

        $this->refreshApplication();
    }

    /**
     * Every /api route (the docs aside) is documented and every documented path exists.
     */
    public function test_the_spec_matches_the_api_routes(): void
    {
        $documented = array_keys(Yaml::parseFile(base_path('docs/openapi/openapi.yaml'))['paths']);

        $apiRoutes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => '/'.$route->uri())
            ->filter(fn (string $uri) => str_starts_with($uri, '/api/') && ! str_starts_with($uri, '/api/auth/docs/'))
            ->unique()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing($apiRoutes, array_values(array_filter(
            $documented,
            fn (string $path) => str_starts_with($path, '/api/'),
        )));

        foreach ($documented as $path) {
            $this->assertTrue(
                collect(Route::getRoutes()->getRoutes())->contains(fn ($route) => '/'.$route->uri() === $path),
                "Documented path {$path} has no route.",
            );
        }
    }

    /**
     * Passport's own routes are off: only the token and authorize endpoints are published, under the API prefix,
     * next to our own userinfo of OpenID Connect.
     */
    public function test_only_the_prefixed_token_and_authorize_endpoints_are_exposed(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());

        $this->assertContains('api/auth/oauth/token', $uris);
        $this->assertContains('api/auth/oauth/authorize', $uris);
        $this->assertEmpty($uris->filter(fn (string $uri) => str_starts_with($uri, 'oauth/')));
        $this->assertEqualsCanonicalizing(
            ['api/auth/oauth/token', 'api/auth/oauth/authorize', 'api/auth/oauth/userinfo'],
            $uris->filter(fn (string $uri) => str_starts_with($uri, 'api/auth/oauth/'))->unique()->values()->all(),
        );
    }
}
