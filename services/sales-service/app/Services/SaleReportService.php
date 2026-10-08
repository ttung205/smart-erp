<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SaleReportItem;

class SaleReportService
{
    /**
     * Ghi nhận / đồng bộ các mặt hàng của đơn bán hàng vào bảng sale_report_items khi đơn được duyệt
     */
    public static function syncOrderToReport(SalesOrder $order): void
    {
        $order->loadMissing(['items', 'customer', 'creator', 'assignee']);

        $salespersonId = $order->assigned_to ?: $order->created_by;

        foreach ($order->items as $item) {
            $qty = (float)($item->quantity ?? 0);
            $deliveredQty = (float)($item->delivered_quantity ?? 0);
            $remainingQty = max(0, $qty - $deliveredQty);
            $price = (float)($item->selling_price ?? $item->unit_price ?? 0);
            $vatPercent = (float)($item->vat_percent ?? 10);
            $subtotal = (float)($item->subtotal ?: ($qty * $price));
            $vatAmount = round($subtotal * ($vatPercent / 100), 2);
            $totalAmount = $subtotal + $vatAmount;
            $deliveryDate = $item->delivery_date ?: $order->order_date;

            $status = 'PENDING';
            if ($deliveredQty >= $qty && $qty > 0) {
                $status = 'DELIVERED';
            } elseif ($deliveredQty > 0) {
                $status = 'PARTIAL';
            }

            SaleReportItem::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                ],
                [
                    'order_number' => $order->order_number,
                    'product_id' => $item->product_id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'quantity' => $qty,
                    'delivered_quantity' => $deliveredQty,
                    'remaining_quantity' => $remainingQty,
                    'selling_price' => $price,
                    'vat' => $vatPercent,
                    'vat_percent' => $vatPercent,
                    'vat_amount' => $vatAmount,
                    'subtotal' => $subtotal,
                    'total_amount' => $totalAmount,
                    'delivery_date' => $deliveryDate,
                    'customer_id' => $order->customer_id,
                    'salesperson_id' => $salespersonId,
                    'status' => $status,
                ]
            );
        }
    }

    /**
     * Cập nhật số lượng đã giao & chưa giao khi kho ghi sổ xuất hàng
     */
    public static function updateItemDelivery(int $orderItemId, float $addedDeliveredQty): void
    {
        $reportItem = SaleReportItem::where('order_item_id', $orderItemId)->first();
        if ($reportItem) {
            $newDelivered = $reportItem->delivered_quantity + $addedDeliveredQty;
            $newRemaining = max(0, $reportItem->quantity - $newDelivered);
            $newStatus = $newDelivered >= $reportItem->quantity && $reportItem->quantity > 0 ? 'DELIVERED' : ($newDelivered > 0 ? 'PARTIAL' : 'PENDING');

            $reportItem->update([
                'delivered_quantity' => $newDelivered,
                'remaining_quantity' => $newRemaining,
                'status' => $newStatus,
            ]);
        }
    }
}
