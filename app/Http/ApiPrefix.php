<?php

namespace App\Http;

/**
 * Prefix of every route of the API: /api/auth/...
 *
 * apidev.ubo.cl is shared by several APIs and ubo-nginx-proxy picks the container by the
 * segment after /api (api-<name>-nginx-prod) without rewriting the path, so the internal and
 * the public URL are the same. Staying under api/* keeps the RFC 9457 errors working with
 * UBO.Common's default configuration.
 */
final class ApiPrefix
{
    public const BASE = 'api/auth';
}
