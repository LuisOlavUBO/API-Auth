<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Grant Client
    |--------------------------------------------------------------------------
    |
    | Client used internally by the /api/auth/v1 endpoints (register, login,
    | refresh) to issue tokens through Passport's password grant. Create it
    | with "php artisan oauth:client <name> --application=api-auth --type=password"
    | and copy the values here.
    |
    */

    'password_client' => [
        'id' => env('PASSPORT_PASSWORD_CLIENT_ID'),
        'secret' => env('PASSPORT_PASSWORD_CLIENT_SECRET'),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    |
    | The scope catalog lives in the "oauth_scopes" table and each role is
    | granted a subset of it ("oauth_scope_role"). Manage them with the
    | "oauth:scope" and "oauth:assign-role" commands. Default scopes are
    | requested when a login sends no "scope"; new users get default_role.
    |
    */

    'default_scopes' => ['user:read'],

    'default_role' => env('OAUTH_DEFAULT_ROLE', 'user'),

    // Application that owns default_role and the clients of /api/auth/v1.
    'application' => env('OAUTH_APPLICATION', 'api-auth'),

];
