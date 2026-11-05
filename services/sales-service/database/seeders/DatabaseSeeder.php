<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Sales không seed dữ liệu: sản phẩm, khách hàng lấy từ catalog-service qua REST rồi snapshot vào chứng từ.
     * Giữ lớp này để `php artisan db:seed` (AUTO_SEED=true trong docker-entrypoint.sh) vẫn chạy được.
     */
    public function run(): void
    {
        //
    }
}