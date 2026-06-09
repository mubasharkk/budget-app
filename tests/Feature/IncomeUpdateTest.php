<?php

namespace Tests\Feature;

use App\Enums\IncomeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_set_monthly_income(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/income', [
                'monthly_income' => 3500.50,
                'income_type' => 'brutto',
                'income_currency' => 'EUR',
            ])
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('3500.50', $user->monthly_income);
        $this->assertSame(IncomeType::Brutto, $user->income_type);
        $this->assertSame('EUR', $user->income_currency);
    }

    public function test_user_can_clear_monthly_income(): void
    {
        $user = User::factory()->create([
            'monthly_income' => 2000,
            'income_type' => IncomeType::Net,
            'income_currency' => 'EUR',
        ]);

        $this->actingAs($user)
            ->patch('/profile/income', [
                'monthly_income' => '',
                'income_type' => 'net',
                'income_currency' => 'EUR',
            ])
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertNull($user->monthly_income);
        $this->assertNull($user->income_type);
    }

    public function test_income_type_required_when_amount_set(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/income', [
                'monthly_income' => 1000,
            ])
            ->assertSessionHasErrors('income_type');
    }
}
