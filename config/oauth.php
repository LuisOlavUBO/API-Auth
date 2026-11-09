<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes (minutes)
    |--------------------------------------------------------------------------
    |
    | The refresh token is renewed on every use, so its lifetime is the
    | inactivity allowed before the user must log in again.
    |
    */

    'access_token_ttl' => (int) env('PASSPORT_ACCESS_TOKEN_TTL', 60),

    'refresh_token_ttl' => (int) env('PASSPORT_REFRESH_TOKEN_TTL', 120),

    'client_token_ttl' => (int) env('PASSPORT_CLIENT_TOKEN_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Token Endpoint Rate Limit
    |--------------------------------------------------------------------------
    |
    | Requests per minute to POST /api/auth/oauth/token, per client_id.
    |
    */

    'token_rate_limit' => (int) env('OAUTH_TOKEN_RATE_LIMIT', 120),

];
