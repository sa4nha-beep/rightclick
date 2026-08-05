# syntax=docker/dockerfile:1
#
# RIGHTCLICK — image aplikasi (PHP-FPM 8.3)
# Dipakai oleh service: app, worker, scheduler.
# Referensi: HS-ARCH-RIGHTCLICK-v1.1 bagian 9.1
#
FROM php:8.3-fpm-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_HOME=/tmp/composer

# Ekstensi wajib:
#   pdo_pgsql, pgsql  → PostgreSQL 16
#   redis             → antrean & cache
#   intl              → dibutuhkan Filament 4
#   bcmath            → aritmetika uang numeric(18,2) tanpa float (DB Design 7)
#   zip, gd, exif     → unggah berkas & aset
#   pcntl             → queue worker (graceful shutdown)
#   opcache           → kinerja pada perangkat keras sasaran i3-7100
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        postgresql-client \
        unzip \
        zip; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-rightclick.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
