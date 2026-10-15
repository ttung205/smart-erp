<?php

namespace Database\Factories;

use App\Models\CustomerReceivable;
use App\Models\VatInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReceivable>
 */
class CustomerReceivableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalAmount = fake()->numberBetween(1_000_000, 100_000_000);

        return [
            'vat_invoice_id' => VatInvoice::factory(),
            'customer_id' => fake()->numberBetween(1, 1000),
            'customer_name' => fake()->company(),
            'customer_tax_code' => fake()->numerify('##########'),
            'invoice_number' => fake()->unique()->numerify('########'),
            'invoice_series' => '1C'.now()->format('y').'TBB',
            'invoice_date' => today(),
            'due_date' => today()->addDays(30),
            'currency' => 'VND',
            'original_amount' => $originalAmount,
            'paid_amount' => 0,
            'outstanding_amount' => $originalAmount,
            'status' => 'OPEN',
        ];
    }
}
