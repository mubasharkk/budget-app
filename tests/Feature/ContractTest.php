<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_contract_scoped_to_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contracts.store'), [
                'name' => 'Internet',
                'amount' => 39.99,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'start_date' => '2026-01-01',
                'status' => 'active',
                'auto_renew' => true,
            ])
            ->assertRedirect(route('contracts.index'));

        $this->assertDatabaseHas('contracts', [
            'name' => 'Internet',
            'user_id' => $user->id,
        ]);
    }

    public function test_projected_monthly_amount_normalizes_by_cycle(): void
    {
        $monthly = Contract::factory()->make(['amount' => 50, 'billing_cycle' => 'monthly']);
        $yearly = Contract::factory()->make(['amount' => 1200, 'billing_cycle' => 'yearly']);

        $this->assertSame(50.0, $monthly->projectedMonthlyAmount());
        $this->assertSame(100.0, $yearly->projectedMonthlyAmount());
        $this->assertSame(100.0, (float) $yearly->projected_monthly_amount);
    }

    public function test_index_only_shows_the_users_own_contracts(): void
    {
        $user = User::factory()->create();
        Contract::factory()->for($user)->create(['name' => 'Mine']);
        Contract::factory()->create(['name' => 'Theirs']);

        $response = $this->actingAs($user)->get(route('contracts.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Contracts/Index')
            ->has('contracts', 1)
            ->where('contracts.0.name', 'Mine')
        );
    }

    public function test_non_owner_cannot_update_or_delete_contract(): void
    {
        $contract = Contract::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->put(route('contracts.update', $contract), [
                'name' => 'Hacked',
                'amount' => 1,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'start_date' => '2026-01-01',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('contracts.destroy', $contract))
            ->assertForbidden();

        $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
    }

    public function test_cancelled_contracts_are_excluded_from_monthly_total(): void
    {
        $user = User::factory()->create();
        Contract::factory()->for($user)->create(['amount' => 30, 'billing_cycle' => 'monthly', 'status' => ContractStatus::Active]);
        Contract::factory()->for($user)->cancelled()->create(['amount' => 999, 'billing_cycle' => 'monthly']);

        $this->actingAs($user)
            ->get(route('contracts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.active_count', 1)
                ->where('summary.monthly_total', fn ($value) => (float) $value === 30.0)
            );
    }
}
