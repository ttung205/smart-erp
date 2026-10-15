<?php

namespace Tests\Unit;

use App\Support\DocumentDate;
use PHPUnit\Framework\TestCase;

class DocumentDateTest extends TestCase
{
    public function test_accepts_vietnamese_and_iso_formats(): void
    {
        $this->assertSame('2026-10-15', DocumentDate::normalize('15/10/2026'));
        $this->assertSame('2026-10-05', DocumentDate::normalize('05/10/2026')); // không bị hiểu thành 10/05
        $this->assertSame('2026-10-15', DocumentDate::normalize('2026-10-15'));
    }

    public function test_falls_back_when_empty_or_invalid(): void
    {
        $this->assertSame('2026-01-01', DocumentDate::normalize(null, '2026-01-01'));
        $this->assertSame('2026-01-01', DocumentDate::normalize('', '2026-01-01'));
        $this->assertSame('2026-01-01', DocumentDate::normalize('31/02/2026', '2026-01-01'));
    }
}
