# syntax=docker/dockerfile:1

################################################################################
# Install Composer dependencies (production — no dev packages).
FROM composer:2 AS prod-deps

WORKDIR /app

RUN --mount=type=bind,source=./composer.json,target=composer.json \
    --mount=type=bind,source=./composer.lock,target=composer.lock \
    --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-interaction --no-scripts --ignore-platform-reqs

################################################################################
# Install Composer dependencies including development packages.
FROM composer:2 AS dev-deps

WORKDIR /app

RUN --mount=type=bind,source=./composer.json,target=composer.json \
    --mount=type=bind,source=./composer.lock,target=composer.lock \
    --mount=type=cache,target=/tmp/cache \
    composer install --no-interaction --no-scripts --ignore-platform-reqs

################################################################################
# Shared runtime base: PHP 8.5.12 + Apache (security release for CVE-2026-109618 /
# CVE-2026-109619). Prefer this patch tag over floating 8.5-apache.
FROM php:8.5.12-apache AS base

# Install PostgreSQL PDO extension (Debian packages + cleanup).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq5 \
        libpq-dev \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql \
    && apt-get purge -y --auto-remove libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf 'variables_order = "EGPCS"\n' > "$PHP_INI_DIR/conf.d/zz-variables-order.ini"

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY public/ /var/www/html/public/
COPY src/ /var/www/html/src/
COPY views/ /var/www/html/views/
COPY config/ /var/www/html/config/
COPY database/ /var/www/html/database/
COPY routes.php phinx.php /var/www/html/
COPY storage/ /var/www/html/storage/

RUN chown -R www-data:www-data /var/www/html/storage \
    && chmod -R ug+rwx /var/www/html/storage

################################################################################
FROM base AS development

COPY tests/ /var/www/html/tests/
COPY phpunit.xml /var/www/html/phpunit.xml
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --from=dev-deps /app/vendor/ /var/www/html/vendor/

################################################################################
FROM development AS test

WORKDIR /var/www/html
RUN ./vendor/bin/phpunit

################################################################################
FROM base AS final

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=prod-deps /app/vendor/ /var/www/html/vendor/

USER www-data
