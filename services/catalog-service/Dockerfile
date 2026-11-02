# Stage 1: cài thư viện PHP bằng Composer (Composer không có trong image cuối)
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-scripts --ignore-platform-reqs

# Stage 2: runtime = PHP-FPM + Nginx, chạy bằng user www-data
FROM php:8.4-fpm-alpine AS runtime
WORKDIR /var/www/html

RUN apk add --no-cache bash curl nginx libzip dos2unix \
    && apk add --no-cache --virtual .build-deps libzip-dev linux-headers \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql zip bcmath sockets pcntl opcache \
    && apk del .build-deps

COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/start-web /usr/local/bin/start-web
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN dos2unix /usr/local/bin/start-web /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/start-web /usr/local/bin/docker-entrypoint.sh

COPY --from=vendor --chown=www-data:www-data /app ./
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

# Sinh lại danh sách package tự đăng ký (bỏ qua các package dev không có trong vendor).
RUN php artisan package:discover --ansi

ENV PORT=8002
EXPOSE 8002

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["start-web"]
