<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Domain event của sản phẩm, ghi qua outbox trong cùng transaction tạo sản phẩm. */
class ProductEvents
{
    /**
     * ProductCreated: inventory-service khởi tạo dòng tồn kho = 0 cho sản phẩm tại mọi kho đang hoạt động.
     * Mọi đường tạo sản phẩm (tạo tay, import Excel, kết quả hỏi hàng) đều phải gọi.
     */
    public static function created(int $productId): void
    {
        $product = DB::table('products')->where('id', $productId)
            ->first(['id', 'sku', 'designation', 'com', 'unit', 'gross_price', 'min_stock']);

        Outbox::record('ProductCreated', 'catalog.product.created', 'Product', $product->id, [
            'product_id' => (int) $product->id,
            'sku' => (string) $product->sku,
            'name' => (string) $product->designation,
            'origin' => $product->com ?: 'Việt Nam',
            'unit' => $product->unit ?: 'Cái',
            'gross_price' => (float) $product->gross_price,
            'min_stock' => (int) $product->min_stock,
        ]);
    }
}
