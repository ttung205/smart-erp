<?php

namespace App\Console\Commands;

use App\Infrastructure\Clients\CatalogClient;
use App\Infrastructure\Clients\IdentityClient;
use App\Infrastructure\Clients\PartnerClient;
use App\Services\ReferenceSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Điền snapshot khách hàng / sản phẩm / tên người dùng cho chứng từ lập trước khi sales-service
 * ngừng đọc thẳng DB catalog & identity. Lấy dữ liệu qua API (X-Internal-Service-Key), chạy lại an toàn:
 * chỉ điền dòng còn trống. Mỗi nhóm ghi trong một transaction (SQLite ghi từng dòng rất chậm).
 */
class BackfillReferenceSnapshots extends Command
{
    protected $signature = 'sales:backfill-reference-snapshots';

    protected $description = 'Điền snapshot khách hàng/sản phẩm/người dùng cho chứng từ cũ qua API catalog, partner, identity';

    public function handle(CatalogClient $catalog, PartnerClient $partners, IdentityClient $identity): int
    {
        $report = [];

        foreach (['quotations', 'sales_orders'] as $table) {
            $rows = DB::table($table)->whereNull('customer_code')->whereNotNull('customer_id')
                ->get(['id', 'customer_id', 'contact_id', 'customer_name']);
            $customers = $partners->customers($rows->pluck('customer_id'));
            $filled = 0;
            DB::beginTransaction();
            foreach ($rows as $row) {
                $customer = $customers[(int) $row->customer_id] ?? null;
                if (! $customer) {
                    continue;
                }
                $columns = ReferenceSnapshot::customerColumns($customer, $row->contact_id);
                if ($row->customer_name) {
                    unset($columns['customer_name']); // giữ tên đã snapshot lúc lập chứng từ
                }
                DB::table($table)->where('id', $row->id)->update($columns);
                $filled++;
            }
            DB::commit();
            $report[] = ["{$table}: khách hàng", $rows->count(), $filled];
        }

        $orders = DB::table('sales_orders')->whereNull('created_by_name')->get(['id', 'created_by', 'assigned_to', 'approved_by']);
        $users = $identity->users($orders->flatMap(fn ($o) => [$o->created_by, $o->assigned_to, $o->approved_by]));
        $name = fn ($id) => is_numeric($id) ? ($users[(int) $id]['name'] ?? null) : null;
        $filled = 0;
        DB::beginTransaction();
        foreach ($orders as $order) {
            if (! $name($order->created_by)) {
                continue;
            }
            DB::table('sales_orders')->where('id', $order->id)->update([
                'created_by_name' => $name($order->created_by),
                'assigned_to_name' => $name($order->assigned_to),
                'approved_by_name' => $name($order->approved_by),
            ]);
            $filled++;
        }
        DB::commit();
        $report[] = ['sales_orders: người tạo/phụ trách/duyệt', $orders->count(), $filled];

        foreach (['quotation_items', 'sales_order_items'] as $table) {
            $rows = DB::table($table)->whereNull('designation')->get();
            $byId = $catalog->productsByIds($rows->pluck('product_id'));
            $bySku = $catalog->productsBySkus($rows->whereNull('product_id')->pluck('sku'));
            $filled = 0;
            DB::beginTransaction();
            foreach ($rows as $row) {
                $product = $byId[(int) $row->product_id] ?? $bySku[mb_strtoupper((string) $row->sku)] ?? null;
                if (! $product) {
                    continue;
                }
                $columns = ReferenceSnapshot::productColumns($product);
                if ((float) $row->gross_price <= 0) {
                    $columns['gross_price'] = (float) ($product['gross_price'] ?? 0);
                }
                if ($table === 'sales_order_items' && $row->description === null) {
                    $columns['description'] = $product['description'] ?? null;
                }
                DB::table($table)->where('id', $row->id)->update($columns);
                $filled++;
            }
            DB::commit();
            $report[] = ["{$table}: sản phẩm", $rows->count(), $filled];
        }

        $this->table(['Nhóm', 'Dòng thiếu snapshot', 'Đã điền'], $report);

        return self::SUCCESS;
    }
}
