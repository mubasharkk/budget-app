<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Models\Contract;
use App\Models\Income;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_totals_count_only_the_users_currency_and_report_the_rest(): void
    {
        $this->travelTo('2026-11-15 12:00:00');
        $user = User::factory()->create(['default_currency' => 'EUR', 'monthly_income' => null]);

        Receipt::factory()->for($user)->create(['receipt_date' => '2026-11-03', 'total_amount' => 50, 'currency' => 'EUR']);
        Receipt::factory()->for($user)->create(['receipt_date' => '2026-11-05', 'total_amount' => 40, 'currency' => 'USD']);
        Contract::factory()->for($user)->create(['amount' => 20, 'currency' => 'EUR', 'billing_cycle' => BillingCycle::Monthly]);
        Contract::factory()->for($user)->create(['amount' => 15, 'currency' => 'USD', 'billing_cycle' => BillingCycle::Monthly]);
        Income::factory()->for($user)->create(['amount' => 100, 'currency' => 'EUR', 'received_on' => '2026-11-01']);
        Income::factory()->for($user)->create(['amount' => 300, 'currency' => 'USD', 'received_on' => '2026-11-02']);

        $leftOut = [['currency' => 'USD', 'spent' => 40, 'contracts' => 15, 'income' => 300]];

        $this->actingAs($user)->getJson(route('dashboard.overview', ['period' => 'month']))
            ->assertOk()
            ->assertJsonPath('current.variable', 50)
            ->assertJsonPath('current.fixed', 20)
            ->assertJsonPath('balance.income', 100)
            ->assertJsonPath('balance.balance', 30)
            ->assertJsonPath('other_currencies', $leftOut);

        $this->actingAs($user)->getJson(route('dashboard.snapshot', ['period' => 'month']))
            ->assertOk()
            ->assertJsonPath('expenses.total', 70)
            ->assertJsonPath('contracts.monthly_fixed', 20)
            ->assertJsonPath('balance.income', 100)
            ->assertJsonPath('expenses.other_currencies', $leftOut);
    }
}
