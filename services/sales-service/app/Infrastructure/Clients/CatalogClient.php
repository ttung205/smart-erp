<?php

namespace App\Infrastructure\Clients;

/** Sản phẩm thuộc catalog-service. */
class CatalogClient extends ServiceClient
{
    public function __construct()
    {
        parent::__construct('catalog-service', (string) config('services.catalog.url'), (int) config('services.catalog.timeout', 3));
    }

    /**
     * @param  iterable<int|string>  $ids
     * @return array<int, array<string, mixed>> theo id
     */
    public function productsByIds(iterable $ids): array
    {
        return collect($this->fetch('ids', $ids))->keyBy(fn (array $p): int => (int) $p['id'])->all();
    }

    /**
     * @param  iterable<string>  $skus
     * @return array<string, array<string, mixed>> theo SKU viết hoa
     */
    public function productsBySkus(iterable $skus): array
    {
        return collect($this->fetch('skus', $skus))->keyBy(fn (array $p): string => mb_strtoupper((string) $p['sku']))->all();
    }

    /** @return list<array<string, mixed>> */
    private function fetch(string $param, iterable $values): array
    {
        $products = [];
        foreach (collect($values)->filter()->unique()->chunk(100) as $chunk) {
            array_push($products, ...($this->getData('/api/products', [$param => $chunk->implode(',')]) ?? []));
        }

        return $products;
    }
}
