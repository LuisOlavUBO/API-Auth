#!/bin/sh
set -e

cd /var/www/html

# Passport keys are not baked into the image. They are mounted in /run/keys and copied
# into storage so they get the 600 permissions Passport requires (bind mounts on Windows are 777).
for key in oauth-private.key oauth-public.key; do
    if [ -f "/run/keys/$key" ]; then
        cp "/run/keys/$key" "storage/$key"
        chown www-data:www-data "storage/$key"
        chmod 600 "storage/$key"
    fi
done

php artisan config:cache
php artisan route:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
