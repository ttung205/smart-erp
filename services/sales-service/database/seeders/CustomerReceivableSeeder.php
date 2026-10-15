<?php

namespace Database\Seeders;

use App\Models\VatInvoice;
use App\Services\CustomerReceivableService;
use Illuminate\Database\Seeder;

class CustomerReceivableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = app(CustomerReceivableService::class);

        VatInvoice::with('salesInvoice')
            ->where('status', 'ISSUED')
            ->each(fn (VatInvoice $invoice) => $service->createForVatInvoice($invoice, $invoice->salesInvoice));
    }
}
