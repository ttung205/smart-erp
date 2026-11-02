<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inquiry_result_records_gross_price_history(): void
    {
        $brandId = DB::table('brands')->insertGetId(['name' => 'SKF', 'price_index' => 1.5, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('products')->insert(['sku' => 'SM456-SHA', 'designation' => 'SM456', 'brand_id' => $brandId, 'gross_price' => 6100000, 'cost_price' => 4575000, 'min_stock' => 0, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);

        $this->withHeaders([...$this->jwtHeaders([], ['PURCHASE']), 'X-Internal-Service-Key' => 'phpunit-internal-key'])
            ->postJson('/api/products/apply-inquiry-result', [
                'inquiry_item_id' => 7, 'sku' => 'SM456-SHA', 'designation' => 'SM456', 'brand_id' => $brandId,
                'buying_price' => 4000000, 'vat' => 10, 'tax' => 0, 'price_index' => 1.5, 'reference' => 'IQ-NCC01-171026',
            ])->assertOk();

        $response = $this->withHeaders($this->jwtHeaders([], ['SALE']))->getJson('/api/products/price-history?sku=SM456-SHA')->assertOk();
        // MySQL trả cột DECIMAL dưới dạng chuỗi ("6600000.00"), nên so sánh theo giá trị số.
        $this->assertEquals(6600000, $response->json('data.product.gross_price'));
        $response->assertJsonCount(1, 'data.histories');
        $this->assertEquals(6100000, $response->json('data.histories.0.old_gross_price'));
        $this->assertEquals(6600000, $response->json('data.histories.0.new_gross_price'));
        $response->assertJsonPath('data.histories.0.source', 'INQUIRY');
        $response->assertJsonPath('data.histories.0.reference', 'IQ-NCC01-171026');
        // Sale không được thấy giá vốn
        $response->assertJsonMissingPath('data.histories.0.new_cost_price');
    }

    public function test_unchanged_price_is_not_recorded(): void
    {
        $id = DB::table('products')->insertGetId(['sku' => 'A-1', 'designation' => 'A', 'gross_price' => 100, 'min_stock' => 0, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);

        $this->withHeaders($this->jwtHeaders(['catalog.edit']))->putJson("/api/products/{$id}", ['gross_price' => 100])->assertOk();
        $this->withHeaders($this->jwtHeaders(['catalog.edit']))->putJson("/api/products/{$id}", ['gross_price' => 120])->assertOk();

        $this->withHeaders($this->jwtHeaders())->getJson('/api/products/price-history?sku=A-1')
            ->assertJsonCount(1, 'data.histories')
            ->assertJsonPath('data.histories.0.source', 'MANUAL');
    }
}
