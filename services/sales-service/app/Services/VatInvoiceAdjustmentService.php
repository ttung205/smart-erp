<?php

namespace App\Services;

use App\Infrastructure\Clients\InternalHttp;
use App\Models\RevenueReportItem;
use App\Models\SaleReturn;
use App\Models\VatInvoice;
use App\Models\VatInvoiceAdjustment;
use App\Models\VatInvoiceItem;
use App\Support\DocumentNumberGenerator;
use App\Support\VatInvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VatInvoiceAdjustmentService
{
    /** @param array<string, mixed> $data */
    public function createDraft(array $data, object $actor): VatInvoiceAdjustment
    {
        return DB::transaction(function () use ($data, $actor): VatInvoiceAdjustment {
            $invoice = VatInvoice::with('items')->lockForUpdate()->findOrFail($data['original_vat_invoice_id']);
            if ($invoice->status !== 'ISSUED' || $invoice->adjustment_id !== null) {
                throw ValidationException::withMessages(['original_vat_invoice_id' => 'Chỉ được điều chỉnh hóa đơn đã phát hành.']);
            }
            $direction = $data['direction'];
            $type = $data['adjustment_type'];
            $sign = $direction === 'INCREASE' ? 1 : -1;
            $rows = [];
            foreach ($data['items'] as $input) {
                $original = $invoice->items->firstWhere('id', $input['original_vat_invoice_item_id']);
                if (! $original) {
                    throw ValidationException::withMessages(['items' => 'Dòng điều chỉnh không thuộc hóa đơn gốc.']);
                }
                $amount = (float) $input['amount'];
                if ($type === 'VAT' && $amount > 100) {
                    throw ValidationException::withMessages(['items' => 'Mức điều chỉnh thuế VAT không được vượt quá 100 điểm phần trăm.']);
                }
                $quantityDelta = $type === 'QUANTITY' ? $sign * $amount : 0;
                $unitPriceDelta = $type === 'PRICE' ? $sign * $amount : 0;
                $vatPercentDelta = $type === 'VAT' ? $sign * $amount : 0;
                $prior = DB::table('vat_invoice_adjustment_items as line')
                    ->join('vat_invoice_adjustments as header', 'header.id', '=', 'line.vat_invoice_adjustment_id')
                    ->where('line.original_vat_invoice_item_id', $original->id)
                    ->where('header.status', 'ISSUED')
                    ->selectRaw('COALESCE(SUM(line.quantity_delta), 0) as quantity_delta, COALESCE(SUM(line.subtotal_delta), 0) as subtotal_delta, COALESCE(SUM(line.vat_delta), 0) as vat_delta, COALESCE(SUM(line.vat_percent_delta), 0) as vat_percent_delta')->first();
                $remainingQuantity = (float) $original->quantity + (float) $prior->quantity_delta;
                $currentSubtotal = (float) $original->subtotal + (float) $prior->subtotal_delta;
                $currentVatPercent = (float) $original->vat_percent + (float) $prior->vat_percent_delta;
                if ($type === 'QUANTITY') {
                    if ($amount > $remainingQuantity + 0.001) {
                        throw ValidationException::withMessages(['items' => 'Số lượng giảm vượt quá số lượng đã xuất hóa đơn còn lại.']);
                    }
                    $ratio = $amount / $remainingQuantity;
                    $subtotalDelta = -round($currentSubtotal * $ratio, 2);
                    $vatDelta = -round(((float) $original->vat_amount + (float) $prior->vat_delta) * $ratio, 2);
                } else {
                    if ($type === 'VAT' && ($remainingQuantity <= 0 || $currentSubtotal <= 0
                        || $currentVatPercent + $vatPercentDelta < 0 || $currentVatPercent + $vatPercentDelta > 100)) {
                        throw ValidationException::withMessages(['items' => 'Mức thuế VAT sau điều chỉnh phải nằm từ 0% đến 100% trên hàng còn hiệu lực.']);
                    }
                    $subtotalDelta = $type === 'PRICE' ? round($unitPriceDelta * $remainingQuantity, 2) : 0;
                    $vatDelta = $type === 'VAT' ? round($currentSubtotal * $vatPercentDelta / 100, 2)
                        : round($subtotalDelta * $currentVatPercent / 100, 2);
                }
                if ($type === 'PRICE' && $remainingQuantity <= 0) {
                    throw ValidationException::withMessages(['items' => 'Dòng hàng đã điều chỉnh hết số lượng.']);
                }
                $rows[] = [
                    'original_vat_invoice_item_id' => $original->id,
                    'original_quantity' => $original->quantity,
                    'original_unit_price' => $original->unit_price,
                    'original_vat_percent' => $original->vat_percent,
                    'quantity_delta' => $quantityDelta, 'unit_price_delta' => $unitPriceDelta,
                    'vat_percent_delta' => $vatPercentDelta,
                    'subtotal_delta' => $subtotalDelta, 'vat_delta' => $vatDelta,
                    'total_delta' => round($subtotalDelta + $vatDelta, 2),
                ];
            }
            $subtotal = round(array_sum(array_column($rows, 'subtotal_delta')), 2);
            $vat = round(array_sum(array_column($rows, 'vat_delta')), 2);
            if (abs($subtotal + $vat) < 0.01) {
                throw ValidationException::withMessages(['items' => 'Chênh lệch điều chỉnh phải khác 0.']);
            }
            $adjustment = VatInvoiceAdjustment::create([
                'adjustment_number' => DocumentNumberGenerator::next('vat-adjustment-'.now()->format('Y'), 'ADJ-'.now()->format('Y').'-', 6),
                'original_vat_invoice_id' => $invoice->id,
                'sale_return_id' => $data['sale_return_id'] ?? null,
                'direction' => $direction, 'adjustment_type' => $type,
                'adjustment_date' => $data['adjustment_date'], 'reason' => $data['reason'],
                'subtotal_delta' => $subtotal, 'vat_delta' => $vat,
                'total_delta' => round($subtotal + $vat, 2), 'status' => 'DRAFT',
                'created_by_id' => $actor->id, 'created_by_name' => $actor->name,
            ]);
            $adjustment->items()->createMany($rows);

            return $adjustment->load(['items.originalItem', 'originalInvoice.items']);
        });
    }

    /** @return array<int, VatInvoiceAdjustment> */
    public function createForReturn(SaleReturn $saleReturn, object $actor): array
    {
        return DB::transaction(function () use ($saleReturn, $actor): array {
            SaleReturn::query()->lockForUpdate()->findOrFail($saleReturn->id);
            $existing = VatInvoiceAdjustment::with(['items.originalItem', 'originalInvoice'])
                ->where('sale_return_id', $saleReturn->id)->get();
            if ($existing->isNotEmpty()) {
                return $existing->all();
            }
            $grouped = [];
            $reservedByVatItem = [];
            foreach ($saleReturn->items as $returnItem) {
                $quantity = (float) $returnItem->quantity;
                $vatItems = VatInvoiceItem::with('vatInvoice')
                    ->where('sales_invoice_item_id', $returnItem->invoice_item_id)
                    ->whereHas('vatInvoice', fn ($query) => $query->where('sales_invoice_id', $returnItem->invoice_id)->where('status', 'ISSUED')->whereNull('adjustment_id'))
                    ->orderBy('id')->get();
                foreach ($vatItems as $vatItem) {
                    $alreadyAdjusted = (float) DB::table('vat_invoice_adjustment_items as item')
                        ->join('vat_invoice_adjustments as adjustment', 'adjustment.id', '=', 'item.vat_invoice_adjustment_id')
                        ->where('item.original_vat_invoice_item_id', $vatItem->id)
                        ->where('adjustment.status', 'ISSUED')->sum('item.quantity_delta');
                    $available = max(0, (float) $vatItem->quantity + $alreadyAdjusted - ($reservedByVatItem[$vatItem->id] ?? 0));
                    $applied = min($available, $quantity);
                    if ($applied > 0) {
                        $reservedByVatItem[$vatItem->id] = ($reservedByVatItem[$vatItem->id] ?? 0) + $applied;
                        $grouped[$vatItem->vat_invoice_id][$vatItem->id] = [
                            'original_vat_invoice_item_id' => $vatItem->id,
                            'amount' => $reservedByVatItem[$vatItem->id],
                        ];
                        $quantity -= $applied;
                    }
                    if ($quantity <= 0) {
                        break;
                    }
                }
                if ($quantity > 0.001) {
                    throw ValidationException::withMessages(['sale_return' => 'Số lượng trả vượt số lượng đã xuất hóa đơn VAT còn được điều chỉnh.']);
                }
            }
            $created = [];
            foreach ($grouped as $invoiceId => $items) {
                $created[] = $this->createDraft([
                    'original_vat_invoice_id' => $invoiceId, 'sale_return_id' => $saleReturn->id,
                    'direction' => 'DECREASE', 'adjustment_type' => 'QUANTITY',
                    'adjustment_date' => now()->toDateString(),
                    'reason' => 'Hàng bán trả lại theo phiếu '.$saleReturn->return_number,
                    'items' => array_values($items),
                ], $actor);
            }

            return $created;
        });
    }

    public function approve(VatInvoiceAdjustment $adjustment, object $actor): VatInvoiceAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor): VatInvoiceAdjustment {
            VatInvoice::query()->lockForUpdate()->findOrFail($adjustment->original_vat_invoice_id);
            $locked = VatInvoiceAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            if ($locked->status !== 'DRAFT') {
                throw ValidationException::withMessages(['status' => 'Chỉ yêu cầu chờ duyệt mới được duyệt.']);
            }
            $locked->update([
                'status' => 'APPROVED',
                'approved_by_id' => $actor->id,
                'approved_by_name' => $actor->name,
                'approved_at' => now(),
            ]);

            return $locked->fresh(['items.originalItem', 'originalInvoice.items']);
        });
    }

    public function issue(VatInvoiceAdjustment $adjustment, string $token, CustomerReceivableService $receivables): VatInvoiceAdjustment
    {
        $adjustment = DB::transaction(function () use ($adjustment): VatInvoiceAdjustment {
            VatInvoice::query()->lockForUpdate()->findOrFail($adjustment->original_vat_invoice_id);
            $locked = VatInvoiceAdjustment::with(['items.originalItem', 'originalInvoice'])
                ->lockForUpdate()->findOrFail($adjustment->id);
            if ($locked->status === 'ISSUED') {
                return $locked->load('issuedInvoice');
            }
            if (! in_array($locked->status, ['APPROVED', 'PENDING_FINANCE'], true)) {
                throw ValidationException::withMessages(['status' => 'Yêu cầu này không thể phát hành.']);
            }
            if ($locked->status === 'APPROVED') {
                foreach ($locked->items as $item) {
                    $prior = DB::table('vat_invoice_adjustment_items as line')
                        ->join('vat_invoice_adjustments as header', 'header.id', '=', 'line.vat_invoice_adjustment_id')
                        ->where('line.original_vat_invoice_item_id', $item->original_vat_invoice_item_id)
                        ->whereIn('header.status', ['ISSUED', 'PENDING_FINANCE'])
                        ->selectRaw('COALESCE(SUM(line.quantity_delta), 0) as quantity_delta, COALESCE(SUM(line.unit_price_delta), 0) as price_delta, COALESCE(SUM(line.subtotal_delta), 0) as subtotal_delta, COALESCE(SUM(line.vat_delta), 0) as vat_delta, COALESCE(SUM(line.vat_percent_delta), 0) as vat_percent_delta')->first();
                    if ($locked->adjustment_type === 'VAT'
                        && abs(round(((float) $item->originalItem->subtotal + (float) $prior->subtotal_delta) * $item->vat_percent_delta / 100, 2) - $item->vat_delta) > 0.01) {
                        throw ValidationException::withMessages(['items' => 'Tiền thuế đã thay đổi do hóa đơn gốc có điều chỉnh mới. Vui lòng lập lại yêu cầu.']);
                    }
                    if ($item->original_quantity + (float) $prior->quantity_delta + $item->quantity_delta < -0.001
                        || $item->original_unit_price + (float) $prior->price_delta + $item->unit_price_delta < -0.001
                        || $item->originalItem->vat_amount + (float) $prior->vat_delta + $item->vat_delta < -0.01
                        || $item->original_vat_percent + (float) $prior->vat_percent_delta + $item->vat_percent_delta < -0.001
                        || $item->original_vat_percent + (float) $prior->vat_percent_delta + $item->vat_percent_delta > 100.001) {
                        throw ValidationException::withMessages(['items' => 'Giá trị giảm vượt quá hóa đơn gốc sau các lần điều chỉnh trước.']);
                    }
                }
                $originalInvoice = $locked->originalInvoice;
                $series = $originalInvoice->invoice_series ?: '1C'.now()->format('y').'TBB';
                $issuedInvoice = VatInvoice::create([
                    'adjustment_id' => $locked->id,
                    'invoice_number' => VatInvoiceNumberGenerator::next($series),
                    'invoice_series' => $series,
                    'invoice_date' => $locked->adjustment_date->toDateString(),
                    'sales_invoice_id' => $originalInvoice->sales_invoice_id,
                    'sales_invoice_number' => $originalInvoice->sales_invoice_number,
                    'order_id' => $originalInvoice->order_id,
                    'order_number' => $originalInvoice->order_number,
                    'customer_id' => $originalInvoice->customer_id,
                    'customer_name' => $originalInvoice->customer_name,
                    'customer_tax_code' => $originalInvoice->customer_tax_code,
                    'customer_address' => $originalInvoice->customer_address,
                    'currency' => $originalInvoice->currency,
                    'exchange_rate' => $originalInvoice->exchange_rate,
                    'subtotal' => $locked->subtotal_delta,
                    'vat_total' => $locked->vat_delta,
                    'total_amount' => $locked->total_delta,
                    'created_by' => $locked->approved_by_name,
                    'status' => 'PENDING_FINANCE',
                    'notes' => $locked->reason,
                ]);
                foreach ($locked->items as $item) {
                    $original = $item->originalItem;
                    VatInvoiceItem::create([
                        'vat_invoice_id' => $issuedInvoice->id,
                        'adjustment_item_id' => $item->id,
                        'sales_invoice_item_id' => $original->sales_invoice_item_id,
                        'order_item_id' => $original->order_item_id,
                        'product_id' => $original->product_id,
                        'sku' => $original->sku,
                        'product_name' => $original->product_name,
                        'quantity' => $locked->adjustment_type === 'PRICE'
                            ? round($item->subtotal_delta / $item->unit_price_delta, 2)
                            : $item->quantity_delta,
                        'unit_price' => $locked->adjustment_type === 'QUANTITY'
                            ? round($item->subtotal_delta / $item->quantity_delta, 2)
                            : $item->unit_price_delta,
                        'subtotal' => $item->subtotal_delta,
                        'vat_percent' => $locked->adjustment_type === 'VAT'
                            ? $item->original_vat_percent + $item->vat_percent_delta
                            : $original->vat_percent,
                        'vat_amount' => $item->vat_delta,
                        'total_amount' => $item->total_delta,
                    ]);
                }
                $locked->update(['status' => 'PENDING_FINANCE']);
            }

            return $locked->load('issuedInvoice.items');
        });
        if ($adjustment->status === 'ISSUED') {
            return $adjustment;
        }
        if (! $adjustment->originalInvoice->finance_synced_at) {
            $receivables->createForVatInvoice(
                $adjustment->originalInvoice,
                $adjustment->originalInvoice->salesInvoice,
                $token
            );
            $adjustment->originalInvoice->update(['finance_synced_at' => now()]);
        }
        InternalHttp::finance($token)
            ->post(config('services.finance.url').'/api/invoice-adjustments/sync', [
                'adjustment_id' => $adjustment->id,
                'original_vat_invoice_id' => $adjustment->original_vat_invoice_id,
                'adjustment_number' => $adjustment->issuedInvoice->invoice_number,
                'adjustment_series' => $adjustment->issuedInvoice->invoice_series,
                'adjustment_date' => $adjustment->adjustment_date->toDateString(),
                'reason' => $adjustment->reason,
                'adjustment_type' => $adjustment->adjustment_type,
                'subtotal_delta' => $adjustment->subtotal_delta, 'vat_delta' => $adjustment->vat_delta,
                'total_delta' => $adjustment->total_delta,
                'currency' => $adjustment->originalInvoice->currency,
                'customer_id' => $adjustment->originalInvoice->customer_id,
            ])->throw();
        DB::transaction(function () use ($adjustment): void {
            $issuedInvoice = $adjustment->issuedInvoice;
            foreach ($adjustment->items as $item) {
                $original = $item->originalItem;
                if (! $adjustment->originalInvoice->sales_invoice_id || ! $original->sales_invoice_item_id) {
                    continue;
                }
                $source = RevenueReportItem::where('vat_invoice_item_id', $original->id)->first();
                RevenueReportItem::firstOrCreate(['vat_invoice_adjustment_item_id' => $item->id], [
                    'invoice_id' => $adjustment->originalInvoice->sales_invoice_id,
                    'invoice_number' => $adjustment->originalInvoice->sales_invoice_number,
                    'invoice_item_id' => $original->sales_invoice_item_id,
                    'vat_invoice_id' => $issuedInvoice->id,
                    'vat_invoice_number' => $issuedInvoice->invoice_number,
                    'vat_invoice_series' => $issuedInvoice->invoice_series,
                    'vat_invoice_item_id' => $issuedInvoice->items->firstWhere('adjustment_item_id', $item->id)->id,
                    'order_id' => $adjustment->originalInvoice->order_id,
                    'order_number' => $adjustment->originalInvoice->order_number,
                    'product_id' => $original->product_id, 'sku' => $original->sku,
                    'product_name' => $original->product_name,
                    'account_code' => '1311', 'is_vat_invoiced' => true,
                    'quantity' => $item->quantity_delta,
                    'invoiced_quantity' => $item->quantity_delta,
                    'remaining_invoice_quantity' => 0,
                    'currency' => $adjustment->originalInvoice->currency,
                    'exchange_rate' => $adjustment->originalInvoice->exchange_rate,
                    'unit_price' => $original->unit_price,
                    'subtotal' => $item->subtotal_delta,
                    'vat_rate' => $original->vat_percent,
                    'vat_amount' => $item->vat_delta,
                    'total_amount' => $item->total_delta,
                    'customer_id' => $adjustment->originalInvoice->customer_id,
                    'salesperson_id' => $source?->salesperson_id,
                    'issue_date' => $source?->issue_date,
                    'vat_invoice_date' => $adjustment->adjustment_date->toDateString(),
                ]);
            }
            $issuedInvoice->update(['status' => 'ISSUED', 'finance_synced_at' => now()]);
            $adjustment->update(['status' => 'ISSUED', 'finance_synced_at' => now()]);
        });

        return $adjustment->fresh(['items.originalItem', 'originalInvoice.items', 'issuedInvoice.items']);
    }
}
