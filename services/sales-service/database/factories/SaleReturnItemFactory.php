<?php

namespace Database\Factories;

use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleReturnRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnItem>
 */
class SaleReturnItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_return_id' => SaleReturn::factory(),
            'sale_return_request_item_id' => SaleReturnRequestItem::factory(),
            'invoice_id' => fake()->numberBetween(1, 100000),
            'invoice_item_id' => fake()->numberBetween(1, 100000),
            'product_id' => fake()->numberBetween(1, 100),
            'invoice_number' => fake()->numerify('INV-2026-######'),
            'sku' => fake()->bothify('SKU-####'),
            'product_name' => fake()->words(3, true),
            'unit' => 'Cái',
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ];
    }
}
