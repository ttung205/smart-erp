<?php

namespace App\Infrastructure\Clients;

/**
 * Khách hàng & người liên hệ. Hiện catalog-service giữ dữ liệu này; khi tách partner-service
 * chỉ cần đổi PARTNER_SERVICE_URL (architecture.md §2.1).
 */
class PartnerClient extends ServiceClient
{
    public function __construct()
    {
        parent::__construct('partner-service', (string) config('services.partner.url'), (int) config('services.partner.timeout', 3));
    }

    /** @return array<string, mixed>|null khách hàng kèm "contacts" */
    public function customer(int $id): ?array
    {
        return $this->getData("/api/customers/{$id}");
    }

    /**
     * @param  iterable<int>  $ids
     * @return array<int, array<string, mixed>> theo id
     */
    public function customers(iterable $ids): array
    {
        $customers = [];
        foreach (collect($ids)->filter()->unique()->chunk(100) as $chunk) {
            foreach ($this->getData('/api/customers', ['ids' => $chunk->implode(',')]) ?? [] as $customer) {
                $customers[(int) $customer['id']] = $customer;
            }
        }

        return $customers;
    }
}
