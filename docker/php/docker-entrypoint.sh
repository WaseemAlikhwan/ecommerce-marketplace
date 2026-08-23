#!/bin/sh
set -eu

# Ensure Laravel writable paths exist and are owned by the PHP-FPM user.
# Bind mounts (esp. Docker Desktop on Windows) can leave root-owned 0755
# directories that block Flysystem from creating products/{id}/.
fix_writable_tree() {
    target="$1"
    mkdir -p "$target"

    if [ "$(id -u)" = "0" ]; then
        chown -R www-data:www-data "$target" 2>/dev/null || true
    fi

    find "$target" -type d -exec chmod 775 {} + 2>/dev/null || true
    find "$target" -type f -exec chmod 664 {} + 2>/dev/null || true
}

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache

fix_writable_tree storage
fix_writable_tree bootstrap/cache

# Do not pre-create storage/app/public/products — Flysystem creates
# products/{product_id} on upload once public is writable by www-data.

if [ -f artisan ] && [ ! -e public/storage ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
fi

exec "$@"
