<?php

namespace App\Services;

use App\Infrastructure\Clients\CatalogClient;
use App\Infrastructure\Clients\IdentityClient;
use App\Infrastructure\Clients\PartnerClient;
use Illuminate\Validation\ValidationException;

/**
 * Lấy dữ liệu từ service sở hữu (catalog, partner, identity) lúc lập chứng từ và trả về các cột
 * snapshot để lưu cứng vào chứng từ (architecture.md §5.1–5.2). Gọi trước khi mở DB transaction.
 */
class ReferenceSnapshot
{
    public function __construct(
        private readonly CatalogClient $catalog,
        private readonly PartnerClient $partners,
        private readonly IdentityClient $identity,
    ) {}

    /** @return array<string, string|null> cột snapshot khách hàng + người liên hệ; 422 nếu khách không tồn tại */
    public function customer(int $customerId, mixed $contactId = null): array
    {
        $customer = $this->partners->customer($customerId);
        if (! $customer) {
            throw ValidationException::withMessages(['customer_id' => 'Khách hàng không tồn tại.']);
        }

        return self::customerColumns($customer, $contactId);
    }

    /** @param  array<string, mixed>  $customer */
    public static function customerColumns(array $customer, mixed $contactId = null): array
    {
        $contact = $contactId ? collect($customer['contacts'] ?? [])->firstWhere('id', (int) $contactId) : null;

        return [
            'customer_code' => $customer['code'] ?? null,
            'customer_name' => $customer['name'] ?? null,
            'customer_tax_code' => $customer['tax_code'] ?? null,
            'customer_address' => $customer['address'] ?? null,
            'customer_phone' => $customer['phone'] ?? null,
            'customer_email' => $customer['email'] ?? null,
            'contact_name' => $contact['name'] ?? null,
            'contact_phone' => $contact['phone'] ?? null,
            'contact_email' => $contact['email'] ?? null,
        ];
    }

    /**
     * Sản phẩm của từng dòng: theo product_id, không có thì theo sku. 422 nếu product_id không tồn tại.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array<int|string, array<string, mixed>|null> cùng key với $items
     */
    public function products(array $items): array
    {
        $ids = collect($items)->pluck('product_id')->filter();
        $skus = collect($items)->filter(fn (array $it): bool => empty($it['product_id']) && ! empty($it['sku']))->pluck('sku');
        $byId = $ids->isEmpty() ? [] : $this->catalog->productsByIds($ids);
        $bySku = $skus->isEmpty() ? [] : $this->catalog->productsBySkus($skus);

        $products = [];
        foreach ($items as $key => $it) {
            if (! empty($it['product_id'])) {
                $products[$key] = $byId[(int) $it['product_id']]
                    ?? throw ValidationException::withMessages(["items.{$key}.product_id" => 'Sản phẩm không tồn tại.']);
            } else {
                $products[$key] = $bySku[mb_strtoupper((string) ($it['sku'] ?? ''))] ?? null;
            }
        }

        return $products;
    }

    /**
     * @param  array<string, mixed>|null  $product
     * @return array<string, mixed> cột snapshot sản phẩm cho dòng chứng từ
     */
    public static function productColumns(?array $product): array
    {
        return [
            'designation' => $product['designation'] ?? null,
            'brand' => $product['brand'] ?? null,
            'com' => $product['com'] ?? null,
            'unit' => $product['unit'] ?? null,
        ];
    }

    public function userName(?int $userId): ?string
    {
        return $this->identity->name($userId);
    }

    /** Form cũ gửi tên/email người phụ trách thay vì id. */
    public function resolveUserId(mixed $input, int $fallback): int
    {
        if (is_numeric($input) && (int) $input > 0) {
            return (int) $input;
        }
        if (is_string($input) && trim($input) !== '') {
            return (int) ($this->identity->findByNameOrEmail($input)['id'] ?? $fallback);
        }

        return $fallback;
    }
}
