<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_can_record_one_time_expense(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => 42.50,
                'currency' => 'EUR',
                'spent_on' => '2026-06-01',
                'description' => 'Client lunch',
                'expense_type' => 'business',
                'notes' => 'Meeting with vendor',
            ])
            ->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 42.50,
            'description' => 'Client lunch',
            'expense_type' => 'business',
        ]);
    }

    public function test_index_only_shows_the_users_own_expenses_with_type_split(): void
    {
        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['expense_type' => 'personal', 'amount' => 30]);
        Expense::factory()->for($user)->create(['expense_type' => 'business', 'amount' => 70]);
        Expense::factory()->create(['description' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('expenses.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Expenses/Index')
                ->has('expenses', 2)
                ->where('summary.total', 100)
                ->where('summary.personal', 30)
                ->where('summary.business', 70)
            );
    }

    public function test_non_owner_cannot_update_or_delete_expense(): void
    {
        $expense = Expense::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->put(route('expenses.update', $expense), [
                'amount' => 1,
                'currency' => 'EUR',
                'spent_on' => '2026-06-01',
                'expense_type' => 'personal',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('expenses.destroy', $expense))
            ->assertForbidden();
    }
}
