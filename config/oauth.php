<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Grant Client
    |--------------------------------------------------------------------------
    |
    | Client used internally by the /api/v1/auth endpoints (register, login,
    | refresh) to issue tokens through Passport's password grant. Create it
    | with "php artisan passport:client --password" and copy the values here.
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
    */

    'access_token_ttl' => (int) env('PASSPORT_ACCESS_TOKEN_TTL', 60),

    'refresh_token_ttl' => (int) env('PASSPORT_REFRESH_TOKEN_TTL', 60 * 24 * 30),

    'client_token_ttl' => (int) env('PASSPORT_CLIENT_TOKEN_TTL', 60),

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

];
