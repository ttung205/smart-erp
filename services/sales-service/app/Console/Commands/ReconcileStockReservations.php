<?php

namespace App\Console\Commands;

use App\Infrastructure\Clients\InternalHttp;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Đối soát hàng đang giữ ở Inventory với chứng từ bán hàng của Sales.
 *
 * Inventory có thể giữ hàng cho một số chứng từ mà Sales không có: Sales timeout / chết sau khi Inventory đã giữ
 * nên chứng từ bị rollback, và lời gọi nhả hàng bù trừ cũng lỗi. Chứng từ đã hủy mà còn giữ hàng cũng vậy.
 * Lệnh này nhả hàng của các chứng từ đó. Nhả là idempotent nên chạy lại nhiều lần vẫn an toàn.
 * Chạy định kỳ bởi scheduler (routes/console.php).
 */
class ReconcileStockReservations extends Command
{
    protected $signature = 'sales:reconcile-reservations
        {--dry-run : Chỉ liệt kê, không nhả hàng}
        {--days=7 : Xét chứng từ giữ hàng trong số ngày gần đây}
        {--min-age=5 : Bỏ qua chứng từ giữ hàng chưa đủ số phút này (có thể đang lập dở)}';

    protected $description = 'Nhả hàng đang giữ ở Inventory cho chứng từ bán hàng không tồn tại hoặc đã hủy';

    public function handle(): int
    {
        $inventoryUrl = rtrim((string) config('services.inventory.url'), '/');

        try {
            $response = InternalHttp::inventory(null)->get("{$inventoryUrl}/api/v1/inventory/stocks/reservations/documents", [
                'reservedFrom' => now()->subDays((int) $this->option('days'))->toIso8601String(),
                'reservedTo' => now()->subMinutes((int) $this->option('min-age'))->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            $this->error("Không đọc được danh sách giữ hàng từ Inventory: {$e->getMessage()}");

            return self::FAILURE;
        }
        if (! $response->successful()) {
            $this->error("Inventory trả về HTTP {$response->status()}.");

            return self::FAILURE;
        }

        /** @var list<array{document_id: string, reserved_at: string}> $documents */
        $documents = collect($response->json('data', []))
            ->filter(fn (array $document): bool => str_starts_with($document['document_id'], 'INV-'))
            ->values()
            ->all();
        $invoices = Invoice::query()
            ->whereIn('invoice_number', array_column($documents, 'document_id'))
            ->pluck('warehouse_status', 'invoice_number');

        $released = 0;
        $failed = 0;
        foreach ($documents as $document) {
            $number = $document['document_id'];
            $reason = match (true) {
                ! $invoices->has($number) => 'chứng từ không tồn tại ở Sales',
                $invoices[$number] === 'CANCELLED' => 'chứng từ đã hủy',
                default => null,
            };
            if ($reason === null) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("[dry-run] {$number}: {$reason}, giữ từ {$document['reserved_at']}");

                continue;
            }

            if ($this->release($inventoryUrl, $number)) {
                $released++;
                Log::warning("[ReconcileReservations] Đã nhả hàng treo của {$number}: {$reason}");
            } else {
                $failed++;
            }
        }

        $this->info('Đã xét '.count($documents)." chứng từ đang giữ hàng; nhả {$released}, lỗi {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function release(string $inventoryUrl, string $number): bool
    {
        try {
            $response = InternalHttp::inventory(null)
                ->post("{$inventoryUrl}/api/v1/inventory/stocks/release/document", ['order_id' => $number]);
        } catch (Throwable $e) {
            $this->warn("Nhả hàng {$number} lỗi: {$e->getMessage()}");

            return false;
        }
        if (! $response->successful()) {
            $this->warn("Nhả hàng {$number} lỗi: HTTP {$response->status()}");

            return false;
        }

        return true;
    }
}
