<?php

namespace Database\Factories;

use App\Models\VatInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VatInvoice>
 */
class VatInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1_000_000, 100_000_000);
        $vatTotal = $subtotal * 0.1;

        return [
            'invoice_number' => fake()->unique()->numerify('########'),
            'invoice_series' => '1C'.now()->format('y').'TBB',
            'invoice_date' => fake()->date(),
            'customer_id' => fake()->numberBetween(1, 1000),
            'customer_name' => fake()->company(),
            'customer_tax_code' => fake()->numerify('##########'),
            'currency' => 'VND',
            'exchange_rate' => 1,
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total_amount' => $subtotal + $vatTotal,
            'created_by' => 'Factory',
            'status' => 'ISSUED',
        ];
    }
}
