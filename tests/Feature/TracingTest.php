<?php

namespace Tests\Feature;

use Illuminate\Routing\Router;
use Tests\TestCase;

class TracingTest extends TestCase
{
    protected const TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    public function test_responses_continue_the_callers_trace(): void
    {
        $this->withHeader('traceparent', '00-'.self::TRACE_ID.'-00f067aa0ba902b7-01')
            ->get('/api/v1/auth/public-key')
            ->assertOk()
            ->assertHeader('X-Trace-Id', self::TRACE_ID);
    }

    public function test_error_responses_carry_the_trace_id(): void
    {
        $this->withHeader('traceparent', '00-'.self::TRACE_ID.'-00f067aa0ba902b7-01')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('X-Trace-Id', self::TRACE_ID);
    }

    /**
     * API-Auth validates its own tokens with Passport, never through UBO.Common.
     */
    public function test_only_the_tracing_module_of_ubo_common_is_loaded(): void
    {
        $this->assertArrayNotHasKey('ubo.auth', app(Router::class)->getMiddleware());
        $this->assertFalse(config('ubo-common.modules.auth'));
        $this->assertFalse(config('ubo-common.modules.api-client'));
    }
}
