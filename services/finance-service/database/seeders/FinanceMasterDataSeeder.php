<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\CashFund;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class FinanceMasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['111', 'Tiền mặt', null, 'ASSET', 'DEBIT', false, false],
            ['1111', 'Tiền mặt VND', '111', 'ASSET', 'DEBIT', true, true],
            ['1112', 'Ngoại tệ tại quỹ', '111', 'ASSET', 'DEBIT', true, true],
            ['112', 'Tiền gửi không kỳ hạn', null, 'ASSET', 'DEBIT', false, false],
            ['1121', 'Tiền gửi ngân hàng VND', '112', 'ASSET', 'DEBIT', true, true],
            ['1122', 'Tiền gửi ngân hàng ngoại tệ', '112', 'ASSET', 'DEBIT', true, true],
            ['113', 'Tiền đang chuyển', null, 'ASSET', 'DEBIT', true, false],
            ['131', 'Phải thu của khách hàng', null, 'ASSET', 'DEBIT', false, false],
            ['1311', 'Phải thu khách hàng đã xuất hóa đơn', '131', 'ASSET', 'DEBIT', true, true],
            ['1312', 'Phải thu khách hàng chưa xuất hóa đơn', '131', 'ASSET', 'DEBIT', true, true],
            ['1331', 'Thuế GTGT được khấu trừ của hàng hóa, dịch vụ', null, 'ASSET', 'DEBIT', true, false],
            ['1332', 'Thuế GTGT được khấu trừ của TSCĐ', null, 'ASSET', 'DEBIT', true, false],
            ['138', 'Phải thu khác', null, 'ASSET', 'DEBIT', true, false],
            ['141', 'Tạm ứng', null, 'ASSET', 'DEBIT', true, false],
            ['152', 'Nguyên liệu, vật liệu', null, 'ASSET', 'DEBIT', true, false],
            ['153', 'Công cụ, dụng cụ', null, 'ASSET', 'DEBIT', true, false],
            ['154', 'Chi phí sản xuất, kinh doanh dở dang', null, 'ASSET', 'DEBIT', true, false],
            ['155', 'Thành phẩm', null, 'ASSET', 'DEBIT', true, false],
            ['156', 'Hàng hóa', null, 'ASSET', 'DEBIT', true, false],
            ['211', 'Tài sản cố định hữu hình', null, 'ASSET', 'DEBIT', true, false],
            ['214', 'Hao mòn tài sản cố định', null, 'ASSET', 'CREDIT', true, false],
            ['242', 'Chi phí trả trước', null, 'ASSET', 'DEBIT', true, false],
            ['331', 'Phải trả cho người bán', null, 'LIABILITY', 'CREDIT', true, false],
            ['3331', 'Thuế GTGT phải nộp', null, 'LIABILITY', 'CREDIT', true, false],
            ['3334', 'Thuế thu nhập doanh nghiệp', null, 'LIABILITY', 'CREDIT', true, false],
            ['3335', 'Thuế thu nhập cá nhân', null, 'LIABILITY', 'CREDIT', true, false],
            ['334', 'Phải trả người lao động', null, 'LIABILITY', 'CREDIT', true, false],
            ['338', 'Phải trả, phải nộp khác', null, 'LIABILITY', 'CREDIT', true, false],
            ['341', 'Vay và nợ thuê tài chính', null, 'LIABILITY', 'CREDIT', true, false],
            ['411', 'Vốn đầu tư của chủ sở hữu', null, 'EQUITY', 'CREDIT', true, false],
            ['421', 'Lợi nhuận sau thuế chưa phân phối', null, 'EQUITY', 'CREDIT', true, false],
            ['511', 'Doanh thu bán hàng và cung cấp dịch vụ', null, 'REVENUE', 'CREDIT', true, false],
            ['515', 'Doanh thu hoạt động tài chính', null, 'REVENUE', 'CREDIT', true, false],
            ['521', 'Các khoản giảm trừ doanh thu', null, 'REVENUE', 'DEBIT', true, false],
            ['632', 'Giá vốn hàng bán', null, 'EXPENSE', 'DEBIT', true, false],
            ['635', 'Chi phí tài chính', null, 'EXPENSE', 'DEBIT', true, false],
            ['641', 'Chi phí bán hàng', null, 'EXPENSE', 'DEBIT', true, false],
            ['642', 'Chi phí quản lý doanh nghiệp', null, 'EXPENSE', 'DEBIT', true, false],
            ['711', 'Thu nhập khác', null, 'REVENUE', 'CREDIT', true, false],
            ['811', 'Chi phí khác', null, 'EXPENSE', 'DEBIT', true, false],
            ['821', 'Chi phí thuế thu nhập doanh nghiệp', null, 'EXPENSE', 'DEBIT', true, false],
            ['911', 'Xác định kết quả kinh doanh', null, 'SUMMARY', 'BOTH', true, false],
        ];

        foreach ($accounts as [$code, $name, $parentCode, $category, $normalBalance, $isPostable, $isCustom]) {
            ChartOfAccount::updateOrCreate(['code' => $code], [
                'name' => $name,
                'parent_id' => $parentCode ? ChartOfAccount::where('code', $parentCode)->value('id') : null,
                'category' => $category,
                'normal_balance' => $normalBalance,
                'is_postable' => $isPostable,
                'is_system' => true,
                'is_custom' => $isCustom,
                'tracks_customer' => str_starts_with($code, '131'),
                'tracks_supplier' => $code === '331',
                'is_active' => true,
                'regime' => 'TT99_2025',
            ]);
        }

        CashFund::updateOrCreate(['code' => 'TM-VND'], [
            'name' => 'Quỹ tiền mặt VND', 'currency' => 'VND',
            'account_id' => ChartOfAccount::where('code', '1111')->value('id'),
            'opening_balance' => 0, 'is_active' => true,
        ]);
        BankAccount::updateOrCreate(['code' => 'VCB-VND'], [
            'bank_name' => 'Vietcombank', 'account_number' => '0123456789',
            'account_holder' => 'CÔNG TY SMART ERP', 'branch' => 'Chi nhánh Hà Nội',
            'currency' => 'VND', 'account_id' => ChartOfAccount::where('code', '1121')->value('id'),
            'opening_balance' => 0, 'is_active' => true,
        ]);
        BankAccount::updateOrCreate(['code' => 'MB-VND'], [
            'bank_name' => 'MB Bank', 'account_number' => '9876543210',
            'account_holder' => 'CÔNG TY SMART ERP', 'branch' => 'Chi nhánh Hà Nội',
            'currency' => 'VND', 'account_id' => ChartOfAccount::where('code', '1121')->value('id'),
            'opening_balance' => 0, 'is_active' => true,
        ]);
    }
}
