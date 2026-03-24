FROM php:8.2-fpm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        gd \
        zip \
        opcache \
    && (pecl install redis && docker-php-ext-enable redis || true) \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS dev
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --ignore-platform-req=ext-redis
COPY . .
RUN mkdir -p /var/www/html/public/uploads/avatars /var/www/html/public/uploads/posts \
    && chown -R www-data:www-data /var/www/html/public/uploads

EXPOSE 9000
CMD ["php-fpm"]

FROM base AS prod
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader --classmap-authoritative --ignore-platform-req=ext-redis
COPY . .
RUN mkdir -p /var/www/html/public/uploads/avatars /var/www/html/public/uploads/posts \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 9000
CMD ["php-fpm"]
