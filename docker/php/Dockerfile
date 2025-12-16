FROM php:8.4-fpm

WORKDIR /app

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

COPY ./uploads.ini "$PHP_INI_DIR/conf.d"

RUN install-php-extensions @composer pdo_pgsql