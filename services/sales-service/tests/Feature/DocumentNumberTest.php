<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Support\DocumentNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_number_follows_customer_and_date_rule_with_letter_suffix(): void
    {
        Carbon::setTestNow('2026-10-08 10:00:00');
        $next = fn () => DocumentNumberGenerator::forCustomer(Quotation::class, 'quotation_number', 'QT', 're-02592');

        $this->assertSame('QT-RE-02592-081026', $next());

        $this->makeQuotation('QT-RE-02592-081026');
        $this->assertSame('QT-RE-02592-081026-A', $next());

        $this->makeQuotation('QT-RE-02592-081026-A');
        $this->assertSame('QT-RE-02592-081026-B', $next());

        // Khách khác hoặc ngày khác thì đánh lại từ số gốc
        $this->assertSame('QT-DI-00001-081026', DocumentNumberGenerator::forCustomer(Quotation::class, 'quotation_number', 'QT', 'DI-00001'));
        Carbon::setTestNow('2026-10-09 10:00:00');
        $this->assertSame('QT-RE-02592-091026', $next());
    }

    private function makeQuotation(string $number): void
    {
        Quotation::create([
            'quotation_number' => $number, 'customer_name' => 'Long Sơn', 'quotation_date' => '2026-10-08',
            'status' => 'DRAFT', 'currency' => 'VND', 'exchange_rate' => 1,
        ]);
    }
}
