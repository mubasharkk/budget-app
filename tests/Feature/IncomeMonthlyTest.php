<?php

namespace Tests\Feature;

use App\Enums\IncomeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeMonthlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_set_monthly_income_from_the_income_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('incomes.monthly.update'), [
                'monthly_income' => 4200,
                'income_type' => 'net',
                'income_currency' => 'USD',
            ])
            ->assertRedirect(route('incomes.index'));

        $user->refresh();

        $this->assertSame('4200.00', $user->monthly_income);
        $this->assertSame(IncomeType::Net, $user->income_type);
        $this->assertSame('USD', $user->income_currency);
    }

    public function test_blank_amount_clears_monthly_income(): void
    {
        $user = User::factory()->create([
            'monthly_income' => 3000,
            'income_type' => IncomeType::Brutto,
            'income_currency' => 'EUR',
        ]);

        $this->actingAs($user)
            ->patch(route('incomes.monthly.update'), ['monthly_income' => ''])
            ->assertRedirect(route('incomes.index'));

        $user->refresh();

        $this->assertNull($user->monthly_income);
        $this->assertNull($user->income_type);
    }

    public function test_index_exposes_current_monthly_income(): void
    {
        $user = User::factory()->create([
            'monthly_income' => 2500,
            'income_type' => IncomeType::Net,
            'income_currency' => 'EUR',
        ]);

        $this->actingAs($user)
            ->get(route('incomes.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Incomes/Index')
                ->where('monthlyIncome.amount', 2500)
                ->where('monthlyIncome.income_type', 'net')
                ->where('monthlyIncome.income_currency', 'EUR')
            );
    }
}
