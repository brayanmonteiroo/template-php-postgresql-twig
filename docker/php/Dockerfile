FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    libpq-dev \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql

WORKDIR /var/www/html
