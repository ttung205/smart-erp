<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\RevenueReportItem;
use App\Models\SalesOrder;
use App\Models\VatInvoice;

class RevenueReportService
{
    /**
     * Ghi nhận dòng doanh thu vào bảng revenue_report_items khi kế toán ghi sổ xuất kho (TK 1312)
     */
    public static function recordGoodsIssue(Invoice $invoice, ?string $posterName = null): void
    {
        $invoice->loadMissing(['items.orderItem', 'deliveryRequest.order']);
        $order = $invoice->deliveryRequest?->order ?: ($invoice->order_id ? SalesOrder::find($invoice->order_id) : null);

        $customerId = $order?->customer_id;
        $salespersonId = $order?->assigned_to ?: $order?->created_by;
        $issueDate = now()->format('Y-m-d');

        foreach ($invoice->items as $item) {
            $qty = (float) ($item->quantity ?? 1);
            $price = (float) ($item->unit_price ?: ($item->selling_price ?: 0));
            $subtotal = (float) ($item->subtotal ?: ($qty * $price));
            $vatRate = (float) ($item->vat_percent ?? 10);
            $vatAmount = round($subtotal * ($vatRate / 100), 2);
            $totalAmount = $subtotal + $vatAmount;

            RevenueReportItem::updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'invoice_item_id' => $item->id,
                ],
                [
                    'invoice_number' => $invoice->invoice_number,
                    'order_id' => $order?->id,
                    'order_number' => $order?->customer_order_number ?: ($order?->order_number ?: $invoice->deliveryRequest?->customer_order_number),
                    'product_id' => $item->product_id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name ?: ($item->designation ?: $item->description),
                    'account_code' => '1312', // Đã xuất kho & Chưa xuất HĐ VAT
                    'is_vat_invoiced' => false,
                    'quantity' => $qty,
                    'invoiced_quantity' => 0,
                    'remaining_invoice_quantity' => $qty,
                    'currency' => $invoice->currency ?: 'VND',
                    'exchange_rate' => (float) ($invoice->exchange_rate ?: 1.0),
                    'unit_price' => $price,
                    'subtotal' => $subtotal,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $totalAmount,
                    'customer_id' => $customerId,
                    'salesperson_id' => $salespersonId,
                    'issue_date' => $issueDate,
                ]
            );
        }
    }

    /**
     * Cập nhật chuyển tài khoản từ TK 1312 sang TK 1311 khi lập hóa đơn VAT
     */
    public static function processVatInvoiceIssued(VatInvoice $vatInvoice, array $createdItems): void
    {
        foreach ($createdItems as $vItem) {
            $reportItem = RevenueReportItem::whereKey($vItem->revenue_report_item_id)
                ->where('account_code', '1312')
                ->lockForUpdate()
                ->first();

            if (! $reportItem) {
                continue;
            }

            $invoicedQty = (float) $vItem->quantity;
            $unitPrice = (float) $reportItem->unit_price;
            $vatRate = (float) $reportItem->vat_rate;

            if ($invoicedQty > (float) $reportItem->remaining_invoice_quantity) {
                throw new \DomainException("Số lượng xuất hóa đơn vượt quá số lượng còn lại của SKU {$reportItem->sku}.");
            }

            if (abs($invoicedQty - (float) $reportItem->remaining_invoice_quantity) < 0.000001) {
                // Xuất hóa đơn đủ toàn bộ số lượng còn lại
                $subtotal = $invoicedQty * $unitPrice;
                $vatAmount = round($subtotal * ($vatRate / 100), 2);
                $totalAmount = $subtotal + $vatAmount;

                $reportItem->update([
                    'account_code' => '1311', // Đã xuất kho & Đã xuất HĐ VAT
                    'is_vat_invoiced' => true,
                    'vat_invoice_id' => $vatInvoice->id,
                    'vat_invoice_number' => $vatInvoice->invoice_number,
                    'vat_invoice_series' => $vatInvoice->invoice_series,
                    'vat_invoice_item_id' => $vItem->id,
                    'vat_invoice_date' => $vatInvoice->invoice_date,
                    'invoiced_quantity' => (float) $reportItem->invoiced_quantity + $invoicedQty,
                    'remaining_invoice_quantity' => 0,
                    'subtotal' => $subtotal,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $totalAmount,
                ]);
            } else {
                // Xuất hóa đơn 1 phần (Partial): Giảm số lượng chưa xuất của dòng 1312 hiện tại
                $newRemain = $reportItem->remaining_invoice_quantity - $invoicedQty;
                $remSub = $newRemain * $unitPrice;
                $remVat = round($remSub * ($vatRate / 100), 2);
                $remTotal = $remSub + $remVat;

                $reportItem->update([
                    'quantity' => $newRemain,
                    'remaining_invoice_quantity' => $newRemain,
                    'subtotal' => $remSub,
                    'vat_amount' => $remVat,
                    'total_amount' => $remTotal,
                ]);

                // Tạo dòng doanh thu mới cho phần đã xuất hóa đơn (TK 1311)
                $invSub = $invoicedQty * $unitPrice;
                $invVat = round($invSub * ($vatRate / 100), 2);
                $invTotal = $invSub + $invVat;

                RevenueReportItem::create([
                    'invoice_id' => $reportItem->invoice_id,
                    'invoice_number' => $reportItem->invoice_number,
                    'invoice_item_id' => $reportItem->invoice_item_id,
                    'vat_invoice_id' => $vatInvoice->id,
                    'vat_invoice_number' => $vatInvoice->invoice_number,
                    'vat_invoice_series' => $vatInvoice->invoice_series,
                    'vat_invoice_item_id' => $vItem->id,
                    'order_id' => $reportItem->order_id,
                    'order_number' => $reportItem->order_number,
                    'product_id' => $reportItem->product_id,
                    'sku' => $reportItem->sku,
                    'product_name' => $reportItem->product_name,
                    'account_code' => '1311', // Đã xuất kho & Đã xuất HĐ VAT
                    'is_vat_invoiced' => true,
                    'quantity' => $invoicedQty,
                    'invoiced_quantity' => $invoicedQty,
                    'remaining_invoice_quantity' => 0,
                    'currency' => $reportItem->currency,
                    'exchange_rate' => $reportItem->exchange_rate,
                    'unit_price' => $unitPrice,
                    'subtotal' => $invSub,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $invVat,
                    'total_amount' => $invTotal,
                    'customer_id' => $reportItem->customer_id,
                    'salesperson_id' => $reportItem->salesperson_id,
                    'issue_date' => $reportItem->issue_date,
                    'vat_invoice_date' => $vatInvoice->invoice_date,
                ]);
            }
        }
    }
}
