<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Sales lấy khách hàng/sản phẩm qua API catalog (không đọc DB catalog) và snapshot vào chứng từ. */
class ReferenceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCT = [
        'id' => 11, 'sku' => 'SKU-11', 'designation' => 'Van bi', 'brand' => 'ABB',
        'description' => 'Van bi DN50', 'com' => 'Ý', 'unit' => 'Cái', 'gross_price' => '150000.00',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.catalog.url' => 'http://catalog.test',
            'services.partner.url' => 'http://catalog.test',
            'services.identity.url' => 'http://identity.test',
        ]);
    }

    private function fakeCatalog(array $products = [self::PRODUCT]): void
    {
        Http::fake([
            'catalog.test/api/customers/7' => Http::response(['success' => true, 'data' => [
                'id' => 7, 'code' => 'KH-007', 'name' => 'Công ty A', 'tax_code' => '0101234567',
                'address' => 'Hà Nội', 'phone' => '0901', 'email' => 'a@example.com',
                'contacts' => [['id' => 3, 'name' => 'Chị Lan', 'phone' => '0911', 'email' => 'lan@example.com']],
            ]]),
            'catalog.test/api/customers/*' => Http::response(['success' => false], 404),
            'catalog.test/api/products*' => Http::response(['success' => true, 'data' => $products]),
        ]);
    }

    private function payload(): array
    {
        return [
            'customer_id' => 7,
            'contact_id' => 3,
            'items' => [['product_id' => 11, 'sku' => 'SKU-11', 'quantity' => 2, 'selling_price' => 100000, 'vat_percent' => 10]],
        ];
    }

    public function test_quotation_snapshots_customer_and_product_from_catalog_api(): void
    {
        $this->fakeCatalog();

        $response = $this->withHeaders($this->jwtHeaders(['quotation.create']))
            ->postJson('/api/quotations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.customer.code', 'KH-007')
            ->assertJsonPath('data.contact.name', 'Chị Lan')
            ->assertJsonPath('data.items.0.brand', 'ABB');

        $this->assertStringStartsWith('QT-KH-007-', $response->json('data.quotation_number'));
        $this->assertDatabaseHas('quotations', [
            'customer_code' => 'KH-007', 'customer_name' => 'Công ty A', 'customer_tax_code' => '0101234567',
            'contact_name' => 'Chị Lan', 'contact_phone' => '0911',
        ]);
        $this->assertDatabaseHas('quotation_items', [
            'product_id' => 11, 'designation' => 'Van bi', 'brand' => 'ABB', 'com' => 'Ý', 'unit' => 'Cái', 'gross_price' => 150000,
        ]);
        // Gọi thay mặt người dùng: chuyển tiếp JWT
        Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), '/api/customers/7') && $r->hasHeader('Authorization'));
    }

    public function test_order_snapshots_customer_product_and_user_names(): void
    {
        $this->fakeCatalog();

        $this->withHeaders($this->jwtHeaders(['order.create']))
            ->postJson('/api/orders', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'Công ty A')
            ->assertJsonPath('data.creator.name', 'Test User');

        $this->assertDatabaseHas('sales_orders', [
            'customer_code' => 'KH-007', 'customer_name' => 'Công ty A', 'contact_phone' => '0911',
            'created_by' => 42, 'created_by_name' => 'Test User', 'assigned_to' => 42, 'assigned_to_name' => 'Test User',
        ]);
        $this->assertDatabaseHas('sales_order_items', [
            'product_id' => 11, 'designation' => 'Van bi', 'description' => 'Van bi DN50', 'gross_price' => 150000,
        ]);
    }

    public function test_unknown_product_is_rejected_with_validation_error(): void
    {
        $this->fakeCatalog(products: []);

        $this->withHeaders($this->jwtHeaders(['quotation.create']))
            ->postJson('/api/quotations', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.product_id']);

        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_unknown_customer_is_rejected_with_validation_error(): void
    {
        $this->fakeCatalog();

        $this->withHeaders($this->jwtHeaders(['order.create']))
            ->postJson('/api/orders', [...$this->payload(), 'customer_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_catalog_outage_returns_dependency_unavailable(): void
    {
        Http::fake(['catalog.test/*' => Http::response(null, 500)]);

        $this->withHeaders($this->jwtHeaders(['quotation.create']))
            ->postJson('/api/quotations', $this->payload())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'DEPENDENCY_UNAVAILABLE');

        $this->assertDatabaseCount('quotations', 0);
    }
}
