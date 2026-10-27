<?php

namespace Tests\Feature;

use App\Messaging\RabbitMqPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Mọi đường tạo sản phẩm ghi ProductCreated vào outbox cùng transaction; relay đẩy lên RabbitMQ. */
class ProductOutboxTest extends TestCase
{
    use RefreshDatabase;

    private function createdEvents(): array
    {
        return DB::table('outbox_events')->where('event_type', 'ProductCreated')->orderBy('id')->get()
            ->map(fn ($e) => json_decode($e->payload, true))->all();
    }

    public function test_store_records_product_created_in_outbox(): void
    {
        $id = $this->withHeaders($this->jwtHeaders(['catalog.create']))->postJson('/api/products', [
            'sku' => 'VB-50', 'designation' => 'Van bi DN50', 'com' => 'Ý', 'unit' => 'Cái', 'gross_price' => 150000, 'min_stock' => 5,
        ])->assertCreated()->json('data.id');

        [$event] = $this->createdEvents();
        $this->assertSame('catalog.product.created', DB::table('outbox_events')->value('routing_key'));
        $this->assertSame('PENDING', DB::table('outbox_events')->value('status'));
        $this->assertSame('catalog-service', $event['producer']);
        $this->assertSame((string) $id, $event['aggregate_id']);
        $this->assertEquals(['product_id' => $id, 'sku' => 'VB-50', 'name' => 'Van bi DN50', 'origin' => 'Ý', 'unit' => 'Cái', 'gross_price' => 150000.0, 'min_stock' => 5], $event['payload']);
    }

    public function test_import_records_event_only_for_new_products(): void
    {
        DB::table('products')->insert(['sku' => 'OLD-1', 'designation' => 'Cũ', 'gross_price' => 1, 'min_stock' => 0, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);

        $this->withHeaders($this->jwtHeaders(['catalog.create']))->postJson('/api/products/import', ['items' => [
            ['sku' => 'OLD-1', 'designation' => 'Cũ cập nhật', 'gross_price' => 2],
            ['sku' => 'NEW-1', 'designation' => 'Mới', 'gross_price' => 3],
        ]])->assertOk();

        $this->assertSame(['NEW-1'], array_column(array_column($this->createdEvents(), 'payload'), 'sku'));
    }

    public function test_inquiry_result_records_event_when_it_creates_a_product(): void
    {
        $brandId = DB::table('brands')->insertGetId(['name' => 'SKF', 'price_index' => 1.5, 'created_at' => now(), 'updated_at' => now()]);
        $payload = ['inquiry_item_id' => 7, 'sku' => 'SM-7', 'designation' => 'SM7', 'brand_id' => $brandId,
            'buying_price' => 100, 'vat' => 10, 'tax' => 0, 'price_index' => 1.5];
        $headers = [...$this->jwtHeaders([], ['PURCHASE']), 'X-Internal-Service-Key' => 'phpunit-internal-key'];

        $this->withHeaders($headers)->postJson('/api/products/apply-inquiry-result', $payload)->assertOk();
        $this->withHeaders($headers)->postJson('/api/products/apply-inquiry-result', [...$payload, 'buying_price' => 120])->assertOk();

        $this->assertCount(1, $this->createdEvents()); // lần 2 chỉ cập nhật giá, không phải sản phẩm mới
    }

    public function test_relay_publishes_pending_events_and_keeps_failed_ones_for_retry(): void
    {
        $this->withHeaders($this->jwtHeaders(['catalog.create']))->postJson('/api/products', ['sku' => 'R-1', 'designation' => 'R'])->assertCreated();

        $failing = $this->mock(RabbitMqPublisher::class);
        $failing->shouldReceive('publish')->once()->andThrow(new \RuntimeException('broker down'));
        $failing->shouldReceive('close');
        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();
        $this->assertDatabaseHas('outbox_events', ['status' => 'FAILED', 'retry_count' => 1, 'last_error' => 'broker down']);
        $this->travel(3)->seconds();

        $publisher = $this->mock(RabbitMqPublisher::class);
        $publisher->shouldReceive('publish')->once()->withArgs(fn ($key, $body) => $key === 'catalog.product.created'
            && json_decode($body, true)['payload']['sku'] === 'R-1');
        $publisher->shouldReceive('close');
        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();
        $this->assertDatabaseHas('outbox_events', ['status' => 'PUBLISHED']);
    }
}
