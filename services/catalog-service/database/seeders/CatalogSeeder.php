<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $cats = [
            ['code' => 'CAT-COMPUTERS', 'name' => 'Máy Tính & Laptop', 'description' => 'Laptop văn phòng, máy trạm và máy tính để bàn B2B.'],
            ['code' => 'CAT-OFFICE', 'name' => 'Thiết Bị Văn Phòng', 'description' => 'Máy in, máy chiếu và phụ kiện văn phòng.'],
            ['code' => 'CAT-PERIPHERALS', 'name' => 'Linh Kiện & Phụ Kiện', 'description' => 'Màn hình, bàn phím, chuột và thiết bị lưu trữ.'],
        ];
        foreach ($cats as $c) {
            DB::table('categories')->updateOrInsert(['code' => $c['code']], $c);
        }

        // Brands
        foreach (['Dell', 'Lenovo', 'HP', 'Logitech', 'Keychron'] as $b) {
            DB::table('brands')->updateOrInsert(['name' => $b], ['name' => $b]);
        }

        // Units
        foreach ([['code' => 'PCS', 'name' => 'Chiếc'], ['code' => 'BOX', 'name' => 'Hộp'], ['code' => 'SET', 'name' => 'Bộ']] as $u) {
            DB::table('units')->updateOrInsert(['code' => $u['code']], $u);
        }

        // Warehouses
        $warehouses = [
            ['code' => 'WH-HN', 'name' => 'Kho Tổng Miền Bắc (Hà Nội)', 'address' => 'Lô 12, Cụm Công nghiệp Từ Liêm, Bắc Từ Liêm, Hà Nội', 'is_active' => 1],
            ['code' => 'WH-HCM', 'name' => 'Kho Trung Chuyển Miền Nam (TP.HCM)', 'address' => 'Số 45, KCN Tân Bình, Tây Thạnh, Tân Phú, TP. Hồ Chí Minh', 'is_active' => 1],
            ['code' => 'WH-DN', 'name' => 'Kho Phân Phối Miền Trung (Đà Nẵng)', 'address' => 'Đường số 3, KCN Hòa Cầm, Cẩm Lệ, TP. Đà Nẵng', 'is_active' => 1],
        ];
        foreach ($warehouses as $w) {
            DB::table('warehouses')->updateOrInsert(['code' => $w['code']], $w);
        }

        // Partners
        $partners = [
            ['code' => 'CUST-FPT', 'name' => 'Tập đoàn Công nghệ FPT', 'type' => 'CUSTOMER', 'tax_code' => '0101248141', 'email' => 'procurement@fpt.com.vn', 'phone' => '02473007300', 'address' => 'Tòa nhà FPT, Cầu Giấy, Hà Nội', 'credit_limit' => 500000000.0],
            ['code' => 'CUST-VIETTEL', 'name' => 'Tập đoàn Công nghiệp - Viễn thông Quân đội (Viettel)', 'type' => 'CUSTOMER', 'tax_code' => '0100109106', 'email' => 'supply@viettel.com.vn', 'phone' => '02462556789', 'address' => 'Lô D26 KĐT mới Cầu Giấy, Hà Nội', 'credit_limit' => 1000000000.0],
            ['code' => 'CUST-CMC', 'name' => 'Tập đoàn Công nghệ CMC', 'type' => 'CUSTOMER', 'tax_code' => '0100244125', 'email' => 'contact@cmc.com.vn', 'phone' => '02437958668', 'address' => 'Tòa nhà CMC, Duy Tân, Cầu Giấy, Hà Nội', 'credit_limit' => 300000000.0],
            ['code' => 'SUPP-DELL-VN', 'name' => 'Công ty TNHH Dell Global B.V. Việt Nam', 'type' => 'SUPPLIER', 'tax_code' => '0304958210', 'email' => 'vietnam_orders@dell.com', 'phone' => '02838237000', 'address' => 'Tòa nhà Bitexco, Q.1, TP.HCM', 'credit_limit' => 0.0],
            ['code' => 'SUPP-LENOVO-VN', 'name' => 'Công ty TNHH Lenovo Việt Nam', 'type' => 'SUPPLIER', 'tax_code' => '0309871234', 'email' => 'b2b@lenovo.com', 'phone' => '02839103888', 'address' => 'Saigon Tower, Q.1, TP.HCM', 'credit_limit' => 0.0],
            ['code' => 'SUPP-SYNNEX-FPT', 'name' => 'Công ty Cổ phần Phân phối Synnex FPT', 'type' => 'SUPPLIER', 'tax_code' => '0103001889', 'email' => 'distribution@synnexfpt.com', 'phone' => '02473006666', 'address' => 'Tòa nhà FPT Cầu Giấy, Hà Nội', 'credit_limit' => 0.0],
        ];
        foreach ($partners as $p) {
            DB::table('partners')->updateOrInsert(['code' => $p['code']], $p);
        }

        // Products
        $catMap = DB::table('categories')->pluck('id', 'code');
        $unitMap = DB::table('units')->pluck('id', 'code');
        $brandMap = DB::table('brands')->pluck('id', 'name');

        $products = [
            ['sku' => 'PROD-LAPTOP-01', 'barcode' => '893601234001', 'name' => 'Dell Latitude 5540 i7-1365U/16GB/512GB', 'category_id' => $catMap['CAT-COMPUTERS'], 'brand_id' => $brandMap['Dell'], 'unit_id' => $unitMap['PCS'], 'cost_price' => 19000000.0, 'gross_price' => 24000000.0, 'min_stock' => 10],
            ['sku' => 'PROD-LAPTOP-02', 'barcode' => '893601234002', 'name' => 'Lenovo ThinkPad T14 Gen 4 i5-1335U/16GB/512GB', 'category_id' => $catMap['CAT-COMPUTERS'], 'brand_id' => $brandMap['Lenovo'], 'unit_id' => $unitMap['PCS'], 'cost_price' => 21500000.0, 'gross_price' => 27500000.0, 'min_stock' => 10],
            ['sku' => 'PROD-MONITOR-01', 'barcode' => '893601234003', 'name' => 'Màn hình Dell UltraSharp U2724D 27 inch 2K 120Hz', 'category_id' => $catMap['CAT-PERIPHERALS'], 'brand_id' => $brandMap['Dell'], 'unit_id' => $unitMap['PCS'], 'cost_price' => 7200000.0, 'gross_price' => 9500000.0, 'min_stock' => 15],
            ['sku' => 'PROD-PRINTER-01', 'barcode' => '893601234004', 'name' => 'Máy in Laser HP LaserJet Pro M404dn Duplex Network', 'category_id' => $catMap['CAT-OFFICE'], 'brand_id' => $brandMap['HP'], 'unit_id' => $unitMap['PCS'], 'cost_price' => 5100000.0, 'gross_price' => 6800000.0, 'min_stock' => 5],
            ['sku' => 'PROD-MOUSE-01', 'barcode' => '893601234005', 'name' => 'Chuột không dây công thái học Logitech MX Master 3S', 'category_id' => $catMap['CAT-PERIPHERALS'], 'brand_id' => $brandMap['Logitech'], 'unit_id' => $unitMap['PCS'], 'cost_price' => 1650000.0, 'gross_price' => 2200000.0, 'min_stock' => 30],
            ['sku' => 'PROD-KEYBOARD-01', 'barcode' => '893601234006', 'name' => 'Bàn phím cơ không dây Keychron K8 Pro RGB Hotswap', 'category_id' => $catMap['CAT-PERIPHERALS'], 'brand_id' => $brandMap['Keychron'], 'unit_id' => $unitMap['PCS'], 'cost_price' => 1800000.0, 'gross_price' => 2450000.0, 'min_stock' => 20],
            ['sku' => 'PROD-SERVER-01', 'barcode' => '893601234007', 'name' => 'Máy chủ Dell PowerEdge R450 Xeon Silver 4310 16GB SAS', 'category_id' => $catMap['CAT-COMPUTERS'], 'brand_id' => $brandMap['Dell'], 'unit_id' => $unitMap['SET'], 'cost_price' => 52000000.0, 'gross_price' => 65000000.0, 'min_stock' => 2],
        ];

        foreach ($products as $p) {
            DB::table('products')->updateOrInsert(['sku' => $p['sku']], $p);
        }
    }
}