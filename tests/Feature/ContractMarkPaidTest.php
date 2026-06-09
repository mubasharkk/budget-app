<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractMarkPaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mark_active_contract_as_paid(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->for($user)->create([
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'next_billing_date' => '2026-06-01',
            'status' => ContractStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('contracts.mark-paid', $contract))
            ->assertRedirect();

        $contract->refresh();

        $this->assertSame('2026-07-01', $contract->next_billing_date->toDateString());
        $this->assertNotNull($contract->last_paid_at);
        $this->assertSame(ContractStatus::Active, $contract->status);
    }

    public function test_mark_paid_cancels_contract_when_next_billing_exceeds_end_date(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->for($user)->create([
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'next_billing_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'status' => ContractStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('contracts.mark-paid', $contract))
            ->assertRedirect();

        $contract->refresh();

        $this->assertSame(ContractStatus::Cancelled, $contract->status);
        $this->assertNotNull($contract->last_paid_at);
    }

    public function test_paused_contract_cannot_be_marked_as_paid(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->for($user)->create([
            'status' => ContractStatus::Paused,
            'next_billing_date' => '2026-06-01',
        ]);

        $this->actingAs($user)
            ->post(route('contracts.mark-paid', $contract))
            ->assertSessionHasErrors('contract');

        $contract->refresh();

        $this->assertSame('2026-06-01', $contract->next_billing_date->toDateString());
        $this->assertNull($contract->last_paid_at);
    }

    public function test_non_owner_cannot_mark_contract_as_paid(): void
    {
        $contract = Contract::factory()->create([
            'next_billing_date' => '2026-06-01',
        ]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post(route('contracts.mark-paid', $contract))
            ->assertForbidden();
    }
}
