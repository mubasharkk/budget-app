<?php

namespace Tests\Unit;

use App\Enums\BillingCycle;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BillingCycleTest extends TestCase
{
    public function test_monthly_factor_for_each_cycle(): void
    {
        $this->assertEqualsWithDelta(52 / 12, BillingCycle::Weekly->toMonthlyFactor(), 0.0001);
        $this->assertEqualsWithDelta(26 / 12, BillingCycle::Biweekly->toMonthlyFactor(), 0.0001);
        $this->assertEqualsWithDelta(1.0, BillingCycle::Monthly->toMonthlyFactor(), 0.0001);
        $this->assertEqualsWithDelta(4 / 12, BillingCycle::Quarterly->toMonthlyFactor(), 0.0001);
        $this->assertEqualsWithDelta(1 / 12, BillingCycle::Yearly->toMonthlyFactor(), 0.0001);
    }

    public function test_to_monthly_amount_normalizes_correctly(): void
    {
        $this->assertSame(100.0, BillingCycle::Yearly->toMonthlyAmount(1200));
        $this->assertSame(100.0, BillingCycle::Quarterly->toMonthlyAmount(300));
        $this->assertSame(50.0, BillingCycle::Monthly->toMonthlyAmount(50));
        $this->assertSame(43.33, BillingCycle::Weekly->toMonthlyAmount(10));
    }

    public function test_to_weekly_amount_normalizes_correctly(): void
    {
        $this->assertSame(10.0, BillingCycle::Weekly->toWeeklyAmount(10));
        $this->assertSame(round(1200 / 52, 2), BillingCycle::Yearly->toWeeklyAmount(1200));
        $this->assertSame(round(50 * 12 / 52, 2), BillingCycle::Monthly->toWeeklyAmount(50));
    }

    public function test_options_returns_value_label_pairs(): void
    {
        $options = BillingCycle::options();

        $this->assertCount(5, $options);
        $this->assertSame(['value' => 'monthly', 'label' => 'Monthly'], $options[2]);
    }

    public function test_next_date_advances_by_one_cycle(): void
    {
        $from = CarbonImmutable::parse('2026-03-15');

        $this->assertSame('2026-03-22', BillingCycle::Weekly->nextDate($from)->toDateString());
        $this->assertSame('2026-03-29', BillingCycle::Biweekly->nextDate($from)->toDateString());
        $this->assertSame('2026-04-15', BillingCycle::Monthly->nextDate($from)->toDateString());
        $this->assertSame('2026-06-15', BillingCycle::Quarterly->nextDate($from)->toDateString());
        $this->assertSame('2027-03-15', BillingCycle::Yearly->nextDate($from)->toDateString());
    }

    public function test_monthly_next_date_does_not_overflow_month_end(): void
    {
        $from = CarbonImmutable::parse('2026-01-31');

        $this->assertSame('2026-02-28', BillingCycle::Monthly->nextDate($from)->toDateString());
    }
}
