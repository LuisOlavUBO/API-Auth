<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | Each UBO.Common module can be switched on or off per application, so an
    | app installs the package once and only loads what it uses. Every module
    | keeps its own configuration file (config/ubo-<module>.php).
    |
    | API-Auth uses Tracing, Problems (RFC 9457 errors of api/*), Health
    | (/api/v1/salud) and SqlServer (SET DATEFORMAT ymd on every sqlsrv
    | connection). It issues the tokens and validates them with Passport, so
    | it must not validate them against itself through the Auth module, and it
    | does not call other UBO APIs (ApiClient). Both are off unless the
    | environment turns them on.
    |
    */

    'modules' => [
        'auth' => (bool) env('UBO_COMMON_AUTH', false),
        'api-client' => (bool) env('UBO_COMMON_API_CLIENT', false),
        'tracing' => (bool) env('UBO_COMMON_TRACING', true),
        'problems' => (bool) env('UBO_COMMON_PROBLEMS', true),
        'health' => (bool) env('UBO_COMMON_HEALTH', true),
        'sqlserver' => (bool) env('UBO_COMMON_SQLSERVER', true),
    ],

];
