<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RollContractBillingDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_billing_date_is_advanced_past_today(): void
    {
        $contract = Contract::factory()->create([
            'billing_cycle' => 'monthly',
            'status' => ContractStatus::Active,
            'next_billing_date' => Carbon::today()->subMonths(2),
            'end_date' => null,
        ]);

        $this->artisan('contracts:roll-billing-dates')->assertSuccessful();

        $contract->refresh();
        $this->assertTrue($contract->next_billing_date->greaterThanOrEqualTo(Carbon::today()));
        $this->assertSame(ContractStatus::Active, $contract->status);
    }

    public function test_future_billing_date_is_left_untouched(): void
    {
        $future = Carbon::today()->addDays(10);
        $contract = Contract::factory()->create([
            'billing_cycle' => 'monthly',
            'status' => ContractStatus::Active,
            'next_billing_date' => $future,
        ]);

        $this->artisan('contracts:roll-billing-dates')->assertSuccessful();

        $contract->refresh();
        $this->assertSame($future->toDateString(), $contract->next_billing_date->toDateString());
    }

    public function test_contract_past_end_date_is_cancelled(): void
    {
        $contract = Contract::factory()->create([
            'billing_cycle' => 'monthly',
            'status' => ContractStatus::Active,
            'next_billing_date' => Carbon::today()->subMonth(),
            'end_date' => Carbon::today()->subWeek(),
        ]);

        $this->artisan('contracts:roll-billing-dates')->assertSuccessful();

        $this->assertSame(ContractStatus::Cancelled, $contract->refresh()->status);
    }
}
