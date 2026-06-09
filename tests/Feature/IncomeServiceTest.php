<?php

namespace Tests\Feature;

use App\Enums\IncomeType;
use App\Models\User;
use App\Services\IncomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_income_not_set(): void
    {
        $user = User::factory()->create();

        $context = app(IncomeService::class)->context($user, 500.0, 400.0, 'month');

        $this->assertNull($context);
    }

    public function test_monthly_context_calculates_percentages_and_remainder(): void
    {
        $user = User::factory()->create([
            'monthly_income' => 4000,
            'income_type' => IncomeType::Net,
            'income_currency' => 'EUR',
        ]);

        $context = app(IncomeService::class)->context($user, 1000.0, 2500.0, 'month');

        $this->assertSame(4000.0, $context['monthly_income']);
        $this->assertSame(4000.0, $context['period_income']);
        $this->assertSame('net', $context['income_type']);
        $this->assertSame(25.0, $context['spend_percent']);
        $this->assertSame(62.5, $context['budgeted_percent']);
        $this->assertSame(3000.0, $context['disposable']);
        $this->assertSame(1500.0, $context['remaining_after_budgets']);
        $this->assertFalse($context['is_over_income']);
        $this->assertFalse($context['budgets_exceed_income']);
    }

    public function test_weekly_context_prorates_monthly_income(): void
    {
        $user = User::factory()->create([
            'monthly_income' => 4330,
            'income_type' => IncomeType::Brutto,
        ]);

        $context = app(IncomeService::class)->context($user, 500.0, 1200.0, 'week');

        $this->assertSame(1000.0, $context['period_income']);
        $this->assertSame(50.0, $context['spend_percent']);
        $this->assertTrue($context['budgets_exceed_income']);
    }
}
