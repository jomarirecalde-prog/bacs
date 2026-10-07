<?php

namespace Tests\Unit;

use App\Services\Payroll\WithholdingTaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithholdingTaxServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_tax_uses_seeded_brackets(): void
    {
        $service = app(WithholdingTaxService::class);

        $this->assertEqualsWithDelta(0.0, $service->monthlyTax(20000, '2026-09-15'), 0.01);
        $this->assertGreaterThan(0, $service->monthlyTax(50000, '2026-09-15'));
    }
}
