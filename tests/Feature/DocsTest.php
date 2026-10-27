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
     * Passport's own routes are off: the token endpoint lives under the API prefix only.
     */
    public function test_only_the_prefixed_token_endpoint_is_exposed(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());

        $this->assertContains('api/auth/oauth/token', $uris);
        $this->assertEmpty($uris->filter(fn (string $uri) => str_starts_with($uri, 'oauth/')));
    }
}
