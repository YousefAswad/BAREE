# syntax=docker/dockerfile:1.7
# Production image: PHP-FPM app. Nginx, Horizon, scheduler and Reverb run as separate containers from the same image.

FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN node scripts/i18n-check.mjs && npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

FROM php:8.5-fpm-alpine AS app
RUN apk add --no-cache icu-libs libzip libpng postgresql-libs poppler-utils \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS icu-dev libzip-dev libpng-dev postgresql-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip bcmath gd pcntl opcache \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del .build \
 && php -r 'exit(version_compare(PHP_VERSION, "8.5.12", ">=") ? 0 : 1);'
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-baree.ini
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN php artisan package:discover --ansi && rm -rf tests load .github docs
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
