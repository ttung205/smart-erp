FROM php:8.4-cli-alpine

WORKDIR /var/www/html

# CaÌ€i Ä‘ÄƒÌ£t runtime dependencies & build dependencies
RUN apk add --no-cache \
    bash \
    curl \
    unzip \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    dos2unix \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        zip \
        bcmath \
        sockets \
        pcntl

# CheÌp Composer tÆ°Ì€ official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# TÃ¢Ì£n duÌ£ng Docker cache cho Composer dependencies
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# CheÌp maÌƒ nguÃ´Ì€n Æ°Ìng duÌ£ng
COPY . .

# HoaÌ€n tÃ¢Ìt autoloader vaÌ€ doÌ£n deÌ£p quyÃªÌ€n thÆ° muÌ£c
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

# CheÌp vaÌ€ phÃ¢n quyÃªÌ€n entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN dos2unix /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

ENV PORT=8001
EXPOSE 8001

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8001"]
