<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $excelPath = base_path('../../data/san_pham_mau.xlsx');
        if (!file_exists($excelPath)) {
            $excelPath = 'e:\\smart-erp\\data\\san_pham_mau.xlsx';
        }

        // Chạy python script để nạp file Excel vào sqlite nếu có openpyxl
        $cmd = "python -c \"
import openpyxl, sqlite3
conn = sqlite3.connect('" . str_replace('\\', '/', database_path('database.sqlite')) . "')
cur = conn.cursor()
wb = openpyxl.load_workbook(r'" . $excelPath . "', data_only=True)
ws = wb['SanPham']
rows = list(ws.iter_rows(values_only=True))
header = [str(h).strip().lower() if h else '' for h in rows[0]]
col_map = {name: idx for idx, name in enumerate(header)}
from datetime import datetime
now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

for row in rows[1:]:
    sku = str(row[col_map['sku']]).strip() if row[col_map['sku']] else ''
    if not sku:
        continue
    desc = str(row[col_map.get('item description', 1)] or '').strip()
    brand_name = str(row[col_map.get('brand', 2)] or '').strip()
    designation = str(row[col_map.get('designation', 3)] or '').strip()
    cat_name = str(row[col_map.get('category', 4)] or '').strip()
    try:
        gross_price = float(row[col_map.get('gross price', 5)] or 0)
    except:
        gross_price = 0.0
    specs = str(row[col_map.get('specs', 6)] or '').strip()

    brand_id = None
    if brand_name:
        cur.execute('SELECT id FROM brands WHERE name = ?', (brand_name,))
        b = cur.fetchone()
        brand_id = b[0] if b else cur.execute('INSERT INTO brands (name, created_at, updated_at) VALUES (?, ?, ?)', (brand_name, now, now)).lastrowid

    cat_id = None
    if cat_name:
        cur.execute('SELECT id FROM categories WHERE name = ?', (cat_name,))
        c = cur.fetchone()
        cat_id = c[0] if c else cur.execute('INSERT INTO categories (code, name, created_at, updated_at) VALUES (?, ?, ?, ?)', (cat_name.lower().replace(' ', '-'), cat_name, now, now)).lastrowid

    cur.execute('''
        INSERT OR REPLACE INTO products (sku, designation, brand_id, category_id, description, com, unit, gross_price, spec, cost_price, min_stock, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ''', (sku, designation, brand_id, cat_id, desc, 'Việt Nam', 'Cái', gross_price, specs, gross_price * 0.75, 10, 'ACTIVE', now, now))

conn.commit()
conn.close()
\"";
        @shell_exec($cmd);

        // Seed default partners (Customers)
        $defaultPartners = [
            [
                'code' => 'EA-02592',
                'name' => 'CÔNG TY TNHH LONG SƠN - NHÀ MÁY XI MĂNG',
                'type' => 'CUSTOMER',
                'tax_code' => '2800123456',
                'email' => 'hop.procurement@longson.com',
                'phone' => '0912 345 678',
                'address' => 'Thị xã Bỉm Sơn, Thanh Hóa',
                'credit_limit' => 500000000,
            ],
            [
                'code' => 'FPT-01',
                'name' => 'Tập đoàn Công nghệ FPT',
                'type' => 'CUSTOMER',
                'tax_code' => '0101248141',
                'email' => 'namnv@fpt.com.vn',
                'phone' => '024 7300 7300',
                'address' => 'Số 10 Phạm Văn Bạch, Cầu Giấy, Hà Nội',
                'credit_limit' => 1000000000,
            ],
            [
                'code' => 'VTL-01',
                'name' => 'Tập đoàn Công nghiệp - Viễn thông Quân đội (Viettel)',
                'type' => 'CUSTOMER',
                'tax_code' => '0100109106',
                'email' => 'minhlh@viettel.com.vn',
                'phone' => '024 6255 6789',
                'address' => 'Lô D26 KĐT mới Cầu Giấy, Hà Nội',
                'credit_limit' => 2000000000,
            ],
            [
                'code' => 'HOAPHAT-01',
                'name' => 'Tập đoàn Hòa Phát',
                'type' => 'CUSTOMER',
                'tax_code' => '0900189284',
                'email' => 'contact@hoaphat.com.vn',
                'phone' => '024 6284 8666',
                'address' => 'KCN Phố Nối A, Hưng Yên',
                'credit_limit' => 3000000000,
            ]
        ];

        foreach ($defaultPartners as $p) {
            DB::table('partners')->updateOrInsert(['code' => $p['code']], array_merge($p, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // Seed default customers and multiple contacts per customer
        $customersData = [
            [
                'code' => 'EA-02592',
                'name' => 'CÔNG TY TNHH LONG SƠN - NHÀ MÁY XI MĂNG',
                'tax_code' => '2800123456',
                'phone' => '0912 345 678',
                'email' => 'hop.procurement@longson.com',
                'address' => 'Thị xã Bỉm Sơn, Thanh Hóa',
                'credit_limit' => 500000000,
                'contacts' => [
                    ['name' => 'Mr. Hợp (Trưởng phòng Mua hàng)', 'position' => 'Trưởng phòng Mua hàng', 'phone' => '0912 345 678', 'email' => 'hop.procurement@longson.com', 'is_primary' => true],
                    ['name' => 'Mr. Tuấn (Kỹ sư cơ điện)', 'position' => 'Kỹ sư kỹ thuật', 'phone' => '0988 765 432', 'email' => 'tuan.tech@longson.com', 'is_primary' => false],
                    ['name' => 'Bà Lê Lan (Kế toán thanh toán)', 'position' => 'Kế toán', 'phone' => '0903 111 222', 'email' => 'lan.le@longson.com', 'is_primary' => false],
                ]
            ],
            [
                'code' => 'FPT-01',
                'name' => 'Tập đoàn Công nghệ FPT',
                'tax_code' => '0101248141',
                'phone' => '024 7300 7300',
                'email' => 'namnv@fpt.com.vn',
                'address' => 'Số 10 Phạm Văn Bạch, Cầu Giấy, Hà Nội',
                'credit_limit' => 1000000000,
                'contacts' => [
                    ['name' => 'Nguyễn Văn Nam (Trưởng phòng Vật tư)', 'position' => 'Trưởng phòng Vật tư', 'phone' => '024 7300 7300', 'email' => 'namnv@fpt.com.vn', 'is_primary' => true],
                    ['name' => 'Trần Thị Thu (Kế toán công nợ)', 'position' => 'Kế toán', 'phone' => '0915 223 344', 'email' => 'thutt@fpt.com.vn', 'is_primary' => false],
                ]
            ],
            [
                'code' => 'VTL-01',
                'name' => 'Tập đoàn Công nghiệp - Viễn thông Quân đội (Viettel)',
                'tax_code' => '0100109106',
                'phone' => '024 6255 6789',
                'email' => 'minhlh@viettel.com.vn',
                'address' => 'Lô D26 KĐT mới Cầu Giấy, Hà Nội',
                'credit_limit' => 2000000000,
                'contacts' => [
                    ['name' => 'Lê Hoàng Minh (Phụ trách vật tư hạ tầng)', 'position' => 'Chuyên viên mua sắm', 'phone' => '024 6255 6789', 'email' => 'minhlh@viettel.com.vn', 'is_primary' => true],
                ]
            ],
            [
                'code' => 'HOAPHAT-01',
                'name' => 'Tập đoàn Hòa Phát',
                'tax_code' => '0900189284',
                'phone' => '024 6284 8666',
                'email' => 'contact@hoaphat.com.vn',
                'address' => 'KCN Phố Nối A, Hưng Yên',
                'credit_limit' => 3000000000,
                'contacts' => [
                    ['name' => 'Vũ Đình Hải (Giám đốc Mua hàng Nhà máy)', 'position' => 'Giám đốc Mua hàng', 'phone' => '0913 888 999', 'email' => 'hai.vd@hoaphat.com.vn', 'is_primary' => true],
                    ['name' => 'Ngô Thị Mai (Quản lý hợp đồng)', 'position' => 'Pháp chế & Hợp đồng', 'phone' => '0945 666 777', 'email' => 'mai.nt@hoaphat.com.vn', 'is_primary' => false],
                ]
            ]
        ];

        foreach ($customersData as $cd) {
            $contacts = $cd['contacts'];
            unset($cd['contacts']);

            $cust = \App\Models\Customer::updateOrCreate(['code' => $cd['code']], $cd);

            foreach ($contacts as $c) {
                \App\Models\CustomerContact::updateOrCreate([
                    'customer_id' => $cust->id,
                    'name' => $c['name'],
                ], array_merge($c, ['customer_id' => $cust->id]));
            }
        }
    }
}