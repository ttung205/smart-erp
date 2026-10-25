#!/bin/sh
set -e

PORT="${PORT:-8002}"

# 1. Chép file .env mẫu nếu chưa có
if [ "${APP_ENV:-production}" != "production" ] && [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

# 2. Tạo APP_KEY nếu trống
if [ -z "$APP_KEY" ]; then
    if [ "${APP_ENV:-production}" = "production" ]; then
        echo "APP_KEY is required in production." >&2
        exit 1
    fi
    if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        php artisan key:generate --force
    fi
fi

# 3. Đợi MySQL sẵn sàng nếu dùng MySQL
if [ "$DB_CONNECTION" = "mysql" ]; then
    echo "[Catalog] Dang cho MySQL tai ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
    php -r '
        $host = getenv("DB_HOST") ?: "mysql";
        $port = (int)(getenv("DB_PORT") ?: 3306);
        for ($i = 0; $i < 45; $i++) {
            $fp = @fsockopen($host, $port, $errno, $errstr, 2);
            if ($fp) { fclose($fp); exit(0); }
            sleep(1);
        }
        fwrite(STDERR, "Khong the ket noi den MySQL tai $host:$port\n");
        exit(1);
    '
    echo "[Catalog] MySQL da san sang."
elif [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    mkdir -p database
    touch database/database.sqlite
fi

# 4. Đảm bảo quyền ghi storage và cache
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# 5. Chay migration và seed nếu khởi chạy API server
if [ "${RUN_MIGRATIONS:-false}" = "true" ] && [ "$1" = "php" ] && [ "$2" = "artisan" ] && [ "$3" = "serve" ]; then
    echo "[Catalog] Thuc thi database migrations..."
    php artisan migrate --force

    if [ "$AUTO_SEED" = "true" ]; then
        echo "[Catalog] Thuc thi seed du lieu..."
        php artisan db:seed --force || true
    fi
fi

exec "$@"
