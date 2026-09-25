<?php

namespace Tests\Feature;

use App\Domain\Analytics\Services\ExpenseOverviewService;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_compares_against_the_previous_month(): void
    {
        $user = User::factory()->create(['monthly_income' => null]);
        Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-10', 'total_amount' => 100]);
        Receipt::factory()->for($user)->create(['receipt_date' => '2026-05-20', 'total_amount' => 50]);
        Contract::factory()->for($user)->create(['amount' => 30, 'billing_cycle' => 'monthly']);

        $summary = app(ExpenseOverviewService::class)
            ->summary($user, 'month', null, CarbonImmutable::parse('2026-06-15'));

        $this->assertSame('2026-06-01', $summary['start']);
        $this->assertSame('2026-06-30', $summary['end']);
        $this->assertEquals(130.0, $summary['current']['total']);
        $this->assertEquals(80.0, $summary['previous_total']);
        $this->assertEquals(50.0, $summary['delta']);
        $this->assertEquals(62.5, $summary['delta_percent']);
        $this->assertEquals(-130.0, $summary['balance']['balance']);
    }
}
