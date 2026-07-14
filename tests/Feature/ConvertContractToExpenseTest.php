<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertContractToExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_a_contract_into_an_expense_and_deletes_the_contract(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->for($user)->create([
            'name' => 'Gym membership',
            'amount' => 29.99,
            'currency' => 'EUR',
            'expense_type' => 'business',
            'next_billing_date' => '2026-08-01',
        ]);

        $this->artisan('contracts:convert-to-expense', ['contract' => $contract->id, '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 29.99,
            'currency' => 'EUR',
            'expense_type' => 'business',
            'spent_on' => '2026-08-01',
            'description' => 'Gym membership',
        ]);
    }

    public function test_keep_option_archives_the_contract_instead_of_deleting(): void
    {
        $contract = Contract::factory()->create(['status' => ContractStatus::Active]);

        $this->artisan('contracts:convert-to-expense', ['contract' => $contract->id, '--keep' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(ContractStatus::Archived, $contract->fresh()->status);
        $this->assertSame(1, Expense::query()->count());
    }

    public function test_date_option_overrides_the_expense_date(): void
    {
        $contract = Contract::factory()->create(['next_billing_date' => '2026-08-01']);

        $this->artisan('contracts:convert-to-expense', ['contract' => $contract->id, '--date' => '2026-06-15', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('expenses', ['spent_on' => '2026-06-15']);
    }

    public function test_dry_run_makes_no_changes(): void
    {
        $contract = Contract::factory()->create();

        $this->artisan('contracts:convert-to-expense', ['contract' => $contract->id, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_it_fails_when_the_contract_does_not_exist(): void
    {
        $this->artisan('contracts:convert-to-expense', ['contract' => 999999, '--force' => true])
            ->assertFailed();
    }
}
