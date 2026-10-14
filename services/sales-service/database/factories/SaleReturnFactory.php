<?php

namespace Database\Factories;

use App\Models\SaleReturn;
use App\Models\SaleReturnRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturn>
 */
class SaleReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_number' => 'SR-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'sale_return_request_id' => SaleReturnRequest::factory(),
            'request_number' => fake()->numerify('SRR-#####-######'),
            'customer_id' => fake()->numberBetween(1, 100),
            'customer_code' => fake()->numerify('KH#####'),
            'customer_name' => fake()->company(),
            'return_date' => now()->toDateString(),
            'technical_warehouse_id' => 1,
            'technical_warehouse_name' => 'Kho kỹ thuật',
            'sales_warehouse_id' => 2,
            'sales_warehouse_name' => 'Kho bán',
            'total_amount' => 0,
            'created_by_id' => 42,
            'created_by_name' => 'Test User',
        ];
    }
}
