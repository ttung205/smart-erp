# Stage 1 (php-base): PHP-FPM + Nginx + extension + cấu hình. Dùng chung cho test và runtime nên hai stage luôn cùng môi trường.
FROM php:8.4-fpm-alpine AS php-base
WORKDIR /var/www/html

RUN apk add --no-cache bash curl nginx libzip dos2unix     && apk add --no-cache --virtual .build-deps libzip-dev linux-headers     && docker-php-ext-install -j$(nproc) pdo pdo_mysql zip bcmath sockets pcntl opcache     && apk del .build-deps

COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/start-web /usr/local/bin/start-web
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN dos2unix /usr/local/bin/start-web /usr/local/bin/docker-entrypoint.sh     && chmod +x /usr/local/bin/start-web /usr/local/bin/docker-entrypoint.sh

# Stage 2 (vendor): cài thư viện PHP bằng Composer (Composer không có trong image cuối)
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .
# Mã test không đi vào image chạy thật
RUN composer dump-autoload --optimize --no-scripts --ignore-platform-reqs     && rm -rf tests

# Stage 3 (test): docker build --target test, chạy test bằng scripts/test.ps1. Có thư viện dev + thư mục tests/.
FROM php-base AS test
# GD chỉ cần cho test: UploadedFile::fake()->image() tạo ảnh giả. Image chạy thật không có GD.
RUN apk add --no-cache libpng libjpeg-turbo freetype     && apk add --no-cache --virtual .gd-build-deps libpng-dev libjpeg-turbo-dev freetype-dev     && docker-php-ext-configure gd --with-freetype --with-jpeg     && docker-php-ext-install -j$(nproc) gd     && apk del .gd-build-deps
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist
COPY . .
RUN composer dump-autoload --no-scripts     && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
# Khóa chỉ dùng cho test (không phải secret thật). Một số test gọi HTTP và đọc file .env như khi chạy thật.
ENV APP_KEY=base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=
RUN echo "APP_KEY=${APP_KEY}" > .env
CMD ["php", "artisan", "test", "--compact"]

# Stage 4 (runtime): stage cuối cùng = image chạy thật (docker compose build mặc định build stage này), user www-data
FROM php-base AS runtime
COPY --from=vendor --chown=www-data:www-data /app ./
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache     && chown -R www-data:www-data storage bootstrap/cache

USER www-data

# Sinh lại danh sách package tự đăng ký (bỏ qua các package dev không có trong vendor).
RUN php artisan package:discover --ansi

ENV PORT=8001
EXPOSE 8001

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["start-web"]
