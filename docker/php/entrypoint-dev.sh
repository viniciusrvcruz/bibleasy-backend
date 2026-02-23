#!/bin/sh
# Development entrypoint: fix permissions, app setup (.env, composer, key), then image entrypoint (automations + nginx/php-fpm)
set -e

# Fix permissions on mounted volume (host files are not www-data)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

cd /var/www/html

# Ensure .env exists
if [ ! -f .env ]; then
  echo "Copying .env.example to .env..."
  cp .env.example .env
fi

echo "Installing dependencies with composer..."
composer install --no-scripts --no-interaction --prefer-dist

# Generate application key (only if APP_KEY is not set or empty)
APP_KEY_VAL=$(grep '^APP_KEY=' .env 2>/dev/null | cut -d '=' -f2- | tr -d ' ')
if [ -z "$APP_KEY_VAL" ]; then
  echo "Generating application key..."
  php artisan key:generate --force
fi

# Image entrypoint runs Laravel automations (migrations, storage:link, etc.) then starts s6 (nginx+php-fpm)
exec docker-php-serversideup-entrypoint "${@:-/init}"
