# syntax=docker/dockerfile:1
# BAREE local development image: PHP CLI + the extensions the project needs + Composer + Node 22 + Laravel installer.
# Nothing here touches the host PHP/Laragon. Override the base with BAREE_PHP_IMAGE (php:8.4-cli-bookworm / php:8.3-cli-bookworm).
ARG PHP_IMAGE=php:8.5-cli-bookworm
FROM node:22-bookworm-slim AS node
FROM composer:2 AS composer
FROM ${PHP_IMAGE}

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_HOME=/opt/composer COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 \
    PATH=/opt/composer/vendor/bin:${PATH}

RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip curl ca-certificates rsync procps postgresql-client poppler-utils \
      libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev libpq-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip bcmath gd pcntl \
 && pecl install redis && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/* \
 && printf 'memory_limit=1G\nupload_max_filesize=60M\npost_max_size=64M\n' > /usr/local/etc/php/conf.d/zz-baree-dev.ini

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
 && composer global require laravel/installer --no-progress

# Build fails (on purpose) if PHP is unsupported by Laravel 13 (8.3-8.5) or below the patched release of its branch
# (php.net advisory: fixed in 8.5.12, 8.4.27, 8.3.36).
RUN php -r '$min=["8.3"=>"8.3.36","8.4"=>"8.4.27","8.5"=>"8.5.12"]; $b=PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION; if(!isset($min[$b])||version_compare(PHP_VERSION,$min[$b],"<")){fwrite(STDERR,"PHP ".PHP_VERSION." is unsupported or unpatched\n");exit(1);} echo "PHP ".PHP_VERSION." OK\n";' \
 && for m in pdo_pgsql intl zip bcmath gd pcntl redis mbstring; do php -m | grep -qix "$m" || { echo "missing PHP extension: $m" >&2; exit 1; }; done \
 && node -v && npm -v && composer --version && laravel --version

WORKDIR /app
