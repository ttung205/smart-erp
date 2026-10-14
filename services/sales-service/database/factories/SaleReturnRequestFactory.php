<?php

namespace Database\Factories;

use App\Models\SaleReturnRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnRequest>
 */
class SaleReturnRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_number' => 'SRR-'.fake()->unique()->numerify('#####').'-'.now()->format('dmy'),
            'customer_id' => fake()->numberBetween(1, 100),
            'customer_code' => fake()->numerify('KH#####'),
            'customer_name' => fake()->company(),
            'reason' => 'Hàng lỗi kỹ thuật',
            'notes' => fake()->optional()->sentence(),
            'status' => SaleReturnRequest::PENDING_INSPECTION,
            'total_amount' => 0,
            'created_by_id' => 42,
            'created_by_name' => 'Test User',
        ];
    }
}
