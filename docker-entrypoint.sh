#!/bin/sh
set -e

# storage/ and bootstrap/cache/ come from the bind mount: PHP-FPM (www-data) needs to write to them.
# On Docker Desktop for Windows chown may have no effect; startup is not aborted because of it.
if [ -d storage ] && [ -d bootstrap/cache ]; then
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || echo "Warning: could not change the owner of storage/ (normal on Windows)"
    chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
fi

# Passport rejects key files whose permissions are not 600/660, and bind mounts on Windows are 777.
# The keys mounted in /run/keys are passed by content instead (PHP-FPM keeps the environment).
if [ -f /run/keys/oauth-private.key ] && [ -f /run/keys/oauth-public.key ]; then
    PASSPORT_PRIVATE_KEY="$(cat /run/keys/oauth-private.key)"
    PASSPORT_PUBLIC_KEY="$(cat /run/keys/oauth-public.key)"
    export PASSPORT_PRIVATE_KEY PASSPORT_PUBLIC_KEY
fi

# Runs the container command: php-fpm by default, or whatever is passed (e.g. php artisan ...)
exec "$@"
