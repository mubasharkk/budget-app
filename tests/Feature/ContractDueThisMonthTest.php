<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractDueThisMonthTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_this_month_sums_only_unpaid_contracts_due_in_calendar_month(): void
    {
        $this->travelTo('2026-06-09');

        $user = User::factory()->create();

        Contract::factory()->for($user)->create([
            'amount' => 50,
            'next_billing_date' => '2026-06-15',
            'last_paid_at' => null,
        ]);

        Contract::factory()->for($user)->create([
            'amount' => 30,
            'next_billing_date' => '2026-06-01',
            'last_paid_at' => '2026-06-01',
        ]);

        Contract::factory()->for($user)->create([
            'amount' => 100,
            'next_billing_date' => '2026-07-01',
        ]);

        Contract::factory()->for($user)->cancelled()->create([
            'amount' => 200,
            'next_billing_date' => '2026-06-20',
        ]);

        $summary = app(ContractBillingService::class)->dueThisMonthSummary($user->id);

        $this->assertSame('2026-06', $summary['month']);
        $this->assertSame(50.0, $summary['total']);
        $this->assertSame(1, $summary['count']);
        $this->assertSame(1, $summary['paid_count']);
    }

    public function test_marking_contract_paid_removes_it_from_due_this_month_total(): void
    {
        $this->travelTo('2026-06-09');

        $user = User::factory()->create();

        $contract = Contract::factory()->for($user)->create([
            'amount' => 45,
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'next_billing_date' => '2026-06-15',
            'status' => ContractStatus::Active,
        ]);

        $this->actingAs($user)
            ->get(route('contracts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.due_this_month', fn ($value) => (float) $value === 45.0)
                ->where('summary.due_this_month_count', 1)
            );

        $this->actingAs($user)
            ->post(route('contracts.mark-paid', $contract))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('contracts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.due_this_month', fn ($value) => (float) $value === 0.0)
                ->where('summary.due_this_month_count', 0)
                ->where('summary.paid_this_month_count', 0)
            );
    }
}
