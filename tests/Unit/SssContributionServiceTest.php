<?php

namespace Tests\Unit;

use App\Services\Payroll\SssContributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SssContributionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_semi_monthly_share_for_compensation(): void
    {
        $service = app(SssContributionService::class);

        $monthly = $service->monthlyEmployeeShare(10000, '2026-09-15');
        $this->assertEqualsWithDelta(450.0, $monthly, 0.01);

        $semi = $service->semiMonthlyEmployeeShare(10000, '2026-09-15');
        $this->assertEqualsWithDelta(225.0, $semi, 0.01);
    }
}
