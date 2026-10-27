<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nhả hàng treo ở Inventory cho chứng từ bán hàng không tồn tại / đã hủy (chạy bởi container sales-scheduler)
Schedule::command('sales:reconcile-reservations')->everyFiveMinutes()->withoutOverlapping();
