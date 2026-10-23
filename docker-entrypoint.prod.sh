#!/bin/sh
set -e

# Fail fast if a critical variable is missing
for var in APP_KEY DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
    eval "value=\${$var:-}"
    if [ -z "$value" ]; then
        echo "ERROR: missing environment variable $var" >&2
        exit 1
    fi
done

# Passport keys are not baked into the image. They are mounted in /run/keys (600 on the host,
# readable here because the entrypoint runs as root) and passed by content.
if [ -z "${PASSPORT_PRIVATE_KEY:-}" ]; then
    if [ ! -f /run/keys/oauth-private.key ] || [ ! -f /run/keys/oauth-public.key ]; then
        echo "ERROR: mount the Passport keys in /run/keys or define PASSPORT_PRIVATE_KEY/PASSPORT_PUBLIC_KEY" >&2
        exit 1
    fi
    PASSPORT_PRIVATE_KEY="$(cat /run/keys/oauth-private.key)"
    PASSPORT_PUBLIC_KEY="$(cat /run/keys/oauth-public.key)"
    export PASSPORT_PRIVATE_KEY PASSPORT_PUBLIC_KEY
fi

echo "Optimizing Laravel for production..."

# Discovers the packages (the build used --no-scripts)
php artisan package:discover --ansi

# Caches config, routes, views and events with this deployment's environment variables
php artisan optimize

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

# The caches were written as root: PHP-FPM workers (www-data) must be able to rewrite them
chown -R www-data:www-data storage bootstrap/cache

# Hands control to the container command (php-fpm) as PID 1
exec "$@"
