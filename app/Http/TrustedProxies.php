<?php

namespace App\Http;

/**
 * Reads TRUSTED_PROXIES: the proxies whose X-Forwarded-* headers are believed.
 */
final class TrustedProxies
{
    /**
     * Empty trusts nobody, "*" trusts every proxy, and anything else is a comma-separated list.
     *
     * @return string|string[]
     */
    public static function parse(?string $value): string|array
    {
        $value = trim((string) $value);

        if ($value === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
