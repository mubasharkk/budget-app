<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_budget_scoped_to_themselves(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'period' => 'monthly',
                'amount' => 250,
                'currency' => 'EUR',
                'starts_on' => '2026-06-01',
            ])
            ->assertRedirect(route('budgets.index', ['period' => 'monthly']));

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => '250.00',
        ]);
    }

    public function test_duplicate_budget_for_same_category_and_period_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Budget::factory()->for($user)->create([
            'category_id' => $category->id,
            'period' => 'monthly',
        ]);

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'period' => 'monthly',
                'amount' => 100,
                'currency' => 'EUR',
                'starts_on' => '2026-06-01',
            ])
            ->assertSessionHasErrors('period');
    }

    public function test_index_only_shows_the_users_own_budgets(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create();
        Budget::factory()->create();

        $this->actingAs($user)
            ->get(route('budgets.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Budgets/Index')
                ->has('budgets', 1)
            );
    }

    public function test_non_owner_cannot_update_or_delete_budget(): void
    {
        $budget = Budget::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->put(route('budgets.update', $budget), [
                'category_id' => null,
                'period' => 'monthly',
                'amount' => 1,
                'currency' => 'EUR',
                'starts_on' => '2026-06-01',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('budgets.destroy', $budget))
            ->assertForbidden();
    }

    public function test_dashboard_budgets_endpoint_returns_progress(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Groceries']);

        Budget::factory()->for($user)->create([
            'category_id' => $category->id,
            'amount' => 500,
            'period' => 'monthly',
            'starts_on' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->getJson('/dashboard/budgets?period=monthly')
            ->assertOk()
            ->assertJsonStructure([
                'budgeted',
                'actual',
                'remaining',
                'over_count',
                'warning_count',
                'items',
            ]);
    }
}
