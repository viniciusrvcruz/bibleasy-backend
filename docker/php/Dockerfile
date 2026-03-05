# Base stage: shared PHP image and extensions
FROM serversideup/php:8.4-fpm-nginx-alpine AS base

USER root

# Configure PHP uploads
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/

USER www-data

# Development target: with dev dependencies, ready for volume mounts
FROM base AS development

USER root

# Serversideup/php Laravel automations
ENV PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    AUTORUN_ENABLED=false \
    AUTORUN_DEBUG=true

# Run entrypoint-dev.sh to install dependencies and run migrations
COPY --chmod=755 docker/php/entrypoint-dev.sh /etc/entrypoint.d/entrypoint-dev.sh

EXPOSE 8080

# Production target: optimized, no dev dependencies
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
