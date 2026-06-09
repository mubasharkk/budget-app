<?php

namespace Tests\Feature;

use App\Enums\BudgetPeriod;
use App\Enums\IncomeType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\BudgetService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedScenario(User $user, Category $groceries, Category $beverages): void
    {
        $receipt = Receipt::factory()->for($user)->create([
            'receipt_date' => '2026-06-10',
            'total_amount' => 100,
        ]);
        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 1, 'unit_price' => 60, 'category_id' => $groceries->id,
        ]);
        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 1, 'unit_price' => 40, 'category_id' => $beverages->id,
        ]);

        Contract::factory()->for($user)->create([
            'amount' => 50, 'billing_cycle' => 'monthly', 'category_id' => $groceries->id,
        ]);
        Contract::factory()->for($user)->create([
            'amount' => 1200, 'billing_cycle' => 'yearly', 'category_id' => $beverages->id,
        ]);
    }

    public function test_category_budget_tracks_fixed_and_variable_actuals(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $beverages = Category::factory()->create(['name' => 'Beverages']);
        $this->seedScenario($user, $groceries, $beverages);

        Budget::factory()->for($user)->create([
            'category_id' => $groceries->id,
            'amount' => 200,
            'period' => BudgetPeriod::Monthly,
            'starts_on' => '2026-06-01',
        ]);

        $summary = app(BudgetService::class)->summary($user->id, BudgetPeriod::Monthly);

        $groceriesRow = collect($summary['items'])->firstWhere('category_name', 'Groceries');
        $this->assertNotNull($groceriesRow);
        $this->assertSame(110.0, $groceriesRow['actual']); // 50 fixed + 60 variable
        $this->assertSame(55.0, $groceriesRow['percent_used']);
        $this->assertSame('on_track', $groceriesRow['status']);

        CarbonImmutable::setTestNow();
    }

    public function test_overall_budget_uses_total_spend(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $beverages = Category::factory()->create(['name' => 'Beverages']);
        $this->seedScenario($user, $groceries, $beverages);

        Budget::factory()->for($user)->overall()->create([
            'amount' => 400,
            'period' => BudgetPeriod::Monthly,
            'starts_on' => '2026-06-01',
        ]);

        $summary = app(BudgetService::class)->summary($user->id, BudgetPeriod::Monthly);

        $overall = collect($summary['items'])->firstWhere('label', 'All categories');
        $this->assertNotNull($overall);
        $this->assertSame(250.0, $overall['actual']);
        $this->assertSame('on_track', $overall['status']); // 62.5% used

        CarbonImmutable::setTestNow();
    }

    public function test_warning_and_over_status_thresholds(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $receipt = Receipt::factory()->for($user)->create([
            'receipt_date' => '2026-06-10',
        ]);
        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 1, 'unit_price' => 110, 'category_id' => $groceries->id,
        ]);

        Budget::factory()->for($user)->create([
            'category_id' => $groceries->id,
            'amount' => 100,
            'period' => BudgetPeriod::Monthly,
            'starts_on' => '2026-06-01',
        ]);

        $summary = app(BudgetService::class)->summary($user->id, BudgetPeriod::Monthly);

        $row = $summary['items'][0];
        $this->assertSame('over', $row['status']);
        $this->assertTrue($row['is_over']);
        $this->assertSame(1, $summary['over_count']);

        CarbonImmutable::setTestNow();
    }

    public function test_projected_spend_extrapolates_variable_costs(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $receipt = Receipt::factory()->for($user)->create([
            'receipt_date' => '2026-06-10',
        ]);
        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 1, 'unit_price' => 30, 'category_id' => $groceries->id,
        ]);

        Budget::factory()->for($user)->create([
            'category_id' => $groceries->id,
            'amount' => 500,
            'period' => BudgetPeriod::Monthly,
            'starts_on' => '2026-06-01',
        ]);

        $summary = app(BudgetService::class)->summary($user->id, BudgetPeriod::Monthly);

        $row = $summary['items'][0];
        $this->assertSame(30.0, $row['actual']);
        $this->assertGreaterThan(30.0, $row['projected']);

        CarbonImmutable::setTestNow();
    }

    public function test_summary_includes_income_context_when_set(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        $user = User::factory()->create([
            'monthly_income' => 5000,
            'income_type' => IncomeType::Net,
        ]);
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $this->seedScenario($user, $groceries, Category::factory()->create());

        Budget::factory()->for($user)->overall()->create([
            'amount' => 1000,
            'period' => BudgetPeriod::Monthly,
            'starts_on' => '2026-06-01',
        ]);

        $summary = app(BudgetService::class)->summary($user->id, BudgetPeriod::Monthly);

        $this->assertNotNull($summary['income']);
        $this->assertSame(5000.0, $summary['income']['period_income']);
        $this->assertSame(20.0, $summary['income']['budgeted_percent']);
        $this->assertSame(5.0, $summary['income']['spend_percent']);

        CarbonImmutable::setTestNow();
    }
}
