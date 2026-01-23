#!/bin/sh
set -e

# Initialize storage directory if empty
# This ensures the volume has the correct directory structure
if [ ! "$(ls -A /var/www/storage 2>/dev/null)" ]; then
  echo "Initializing storage directory..."
  cp -R /var/www/storage-init/. /var/www/storage
  chown -R www-data:www-data /var/www/storage
fi

# Remove storage-init directory if exists
rm -rf /var/www/storage-init 2>/dev/null || true

# Run Laravel migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear and cache configurations for better performance
echo "Caching configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run the default command (e.g., php-fpm)
exec "$@"
