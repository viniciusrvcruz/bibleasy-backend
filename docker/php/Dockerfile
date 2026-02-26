# -----------------------------------------------------------------------------
# Base stage: shared PHP image and extensions
# -----------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx-alpine AS base

USER root

# Configure PHP uploads
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/

USER www-data

# -----------------------------------------------------------------------------
# Development target: with dev dependencies, ready for volume mounts
# -----------------------------------------------------------------------------
FROM base AS development

USER root

# Dev entrypoint: fix permissions on mounted volume, run composer install, then image entrypoint
# (No COPY/composer here: volume ./:/var/www/html overwrites it; entrypoint-dev.sh runs composer install)
COPY docker/php/entrypoint-dev.sh /usr/local/bin/entrypoint-dev.sh
RUN chmod +x /usr/local/bin/entrypoint-dev.sh

# Serversideup/php Laravel automations
ENV PHP_OPCACHE_ENABLE=true \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_VIEW_CACHE=false \
    AUTORUN_LARAVEL_CONFIG_CACHE=false \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_MIGRATION_FORCE=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true

# Run entrypoint as root so it can chown; image entrypoint drops to www-data for services
USER root

ENTRYPOINT ["/usr/local/bin/entrypoint-dev.sh"]
CMD ["/init"]

EXPOSE 8080

# -----------------------------------------------------------------------------
# Production target: optimized, no dev dependencies
# -----------------------------------------------------------------------------
FROM base AS production

USER root

# Copy composer files first for better caching
COPY --chown=www-data:www-data composer.json composer.lock ./

# Install dependencies without dev packages
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY --chown=www-data:www-data . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Set permissions for Laravel directories
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

EXPOSE 8080
