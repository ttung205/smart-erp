<?php

namespace Database\Factories;

use App\Models\SaleReturnRequest;
use App\Models\SaleReturnRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnRequestItem>
 */
class SaleReturnRequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_return_request_id' => SaleReturnRequest::factory(),
            'invoice_id' => 1,
            'invoice_item_id' => fake()->unique()->numberBetween(1, 100000),
            'product_id' => fake()->numberBetween(1, 100),
            'invoice_number' => fake()->numerify('INV-######'),
            'sku' => fake()->bothify('SKU-####'),
            'product_name' => fake()->words(3, true),
            'unit' => 'Cái',
            'delivered_quantity' => 5,
            'requested_quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ];
    }
}
