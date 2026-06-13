#!/usr/bin/env sh
set -eu

cd /var/www/html

role="${LARAVEL_BOOTSTRAP_ROLE:-worker}"
db_host="${DB_HOST:-db}"
db_port="${DB_PORT:-3306}"
db_user="${DB_USERNAME:-app}"
db_password="${DB_PASSWORD:-password}"
db_name="${DB_DATABASE:-yandex_reviews}"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R a+rwX storage bootstrap/cache

echo "Waiting for MySQL at ${db_host}:${db_port}..."
until mysqladmin --ssl=0 ping --host="${db_host}" --port="${db_port}" --user="${db_user}" --password="${db_password}" --protocol=tcp --silent >/dev/null 2>&1; do
    sleep 2
done

php artisan optimize:clear >/dev/null 2>&1 || true

if [ "$role" = "primary" ]; then
    if [ -f .env ] && grep -Eq '^APP_KEY=$' .env; then
        echo "Generating Laravel application key..."
        php artisan key:generate --force --no-interaction
    fi

    echo "Running Laravel migrations..."
    php artisan migrate --force --no-interaction

    echo "Running Laravel seeders..."
    php artisan db:seed --force --no-interaction
else
    echo "Waiting for Laravel database schema..."
    until mysql --ssl=0 --host="${db_host}" --port="${db_port}" --user="${db_user}" --password="${db_password}" --protocol=tcp "${db_name}" --execute="SELECT 1 FROM sessions LIMIT 1" >/dev/null 2>&1; do
        sleep 2
    done
fi

exec "$@"
