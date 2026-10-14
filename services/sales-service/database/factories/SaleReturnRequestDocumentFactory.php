<?php

namespace Database\Factories;

use App\Models\SaleReturnRequest;
use App\Models\SaleReturnRequestDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnRequestDocument>
 */
class SaleReturnRequestDocumentFactory extends Factory
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
            'invoice_id' => fake()->numberBetween(1, 100000),
            'invoice_number' => fake()->unique()->numerify('INV-2026-######'),
        ];
    }
}
