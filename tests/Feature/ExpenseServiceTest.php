<?php

namespace Tests\Feature;

use App\Enums\ExpenseType;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\ExpenseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedScenario(User $user, Category $groceries, Category $beverages): void
    {
        // In-range receipt (June) totalling 100, split across two categories.
        $inRange = Receipt::factory()->for($user)->create([
            'receipt_date' => '2026-06-10',
            'total_amount' => 100,
        ]);
        ReceiptItem::factory()->for($inRange)->create([
            'quantity' => 1, 'unit_price' => 60, 'category_id' => $groceries->id,
        ]);
        ReceiptItem::factory()->for($inRange)->create([
            'quantity' => 1, 'unit_price' => 40, 'category_id' => $beverages->id,
        ]);

        // Out-of-range receipt (May) must be ignored.
        $outRange = Receipt::factory()->for($user)->create([
            'receipt_date' => '2026-05-10',
            'total_amount' => 999,
        ]);
        ReceiptItem::factory()->for($outRange)->create([
            'quantity' => 1, 'unit_price' => 999, 'category_id' => $groceries->id,
        ]);

        // Active contracts: monthly 50 (Groceries) + yearly 1200 (Beverages → 100/mo).
        Contract::factory()->for($user)->create([
            'amount' => 50, 'billing_cycle' => 'monthly', 'category_id' => $groceries->id,
        ]);
        Contract::factory()->for($user)->create([
            'amount' => 1200, 'billing_cycle' => 'yearly', 'category_id' => $beverages->id,
        ]);
        // Cancelled contract must not count.
        Contract::factory()->for($user)->cancelled()->create([
            'amount' => 777, 'billing_cycle' => 'monthly', 'category_id' => $groceries->id,
        ]);
    }

    public function test_monthly_overview_combines_fixed_and_variable(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $beverages = Category::factory()->create(['name' => 'Beverages']);
        $this->seedScenario($user, $groceries, $beverages);

        $overview = (new ExpenseService)->overview(
            $user->id,
            CarbonImmutable::parse('2026-06-01')->startOfDay(),
            CarbonImmutable::parse('2026-06-30')->endOfDay(),
            'month',
        );

        $this->assertSame(100.0, $overview['variable']);
        $this->assertSame(150.0, $overview['fixed']); // 50 + (1200/12)
        $this->assertSame(250.0, $overview['total']);

        $byCategory = collect($overview['by_category'])->keyBy('category');
        $this->assertSame(110.0, $byCategory['Groceries']['total']); // 50 fixed + 60 variable
        $this->assertSame(140.0, $byCategory['Beverages']['total']); // 100 fixed + 40 variable
    }

    public function test_weekly_overview_normalizes_fixed_cost_to_a_week(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $beverages = Category::factory()->create(['name' => 'Beverages']);
        $this->seedScenario($user, $groceries, $beverages);

        $overview = (new ExpenseService)->overview(
            $user->id,
            CarbonImmutable::parse('2026-06-08')->startOfDay(),
            CarbonImmutable::parse('2026-06-14')->endOfDay(),
            'week',
        );

        // 50/mo → 50*12/52, 1200/yr → 1200/52
        $expectedFixed = round(50 * 12 / 52, 2) + round(1200 / 52, 2);
        $this->assertEqualsWithDelta($expectedFixed, $overview['fixed'], 0.01);
    }

    public function test_overview_filters_by_expense_type(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $personalReceipt = Receipt::factory()->for($user)->create([
            'receipt_date' => '2026-06-10',
            'total_amount' => 100,
            'expense_type' => 'personal',
        ]);
        ReceiptItem::factory()->for($personalReceipt)->create([
            'quantity' => 1, 'unit_price' => 100, 'category_id' => $groceries->id,
        ]);

        $businessReceipt = Receipt::factory()->for($user)->create([
            'receipt_date' => '2026-06-12',
            'total_amount' => 300,
            'expense_type' => 'business',
        ]);
        ReceiptItem::factory()->for($businessReceipt)->create([
            'quantity' => 1, 'unit_price' => 300, 'category_id' => $groceries->id,
        ]);

        Contract::factory()->for($user)->create([
            'amount' => 50, 'billing_cycle' => 'monthly',
            'category_id' => $groceries->id, 'expense_type' => 'personal',
        ]);
        Contract::factory()->for($user)->create([
            'amount' => 20, 'billing_cycle' => 'monthly',
            'category_id' => $groceries->id, 'expense_type' => 'business',
        ]);

        $service = new ExpenseService;
        $start = CarbonImmutable::parse('2026-06-01')->startOfDay();
        $end = CarbonImmutable::parse('2026-06-30')->endOfDay();

        $business = $service->overview($user->id, $start, $end, 'month', ExpenseType::Business);
        $this->assertSame(300.0, $business['variable']);
        $this->assertSame(20.0, $business['fixed']);

        $personal = $service->overview($user->id, $start, $end, 'month', ExpenseType::Personal);
        $this->assertSame(100.0, $personal['variable']);
        $this->assertSame(50.0, $personal['fixed']);

        $all = $service->overview($user->id, $start, $end, 'month');
        $this->assertSame(400.0, $all['variable']);
        $this->assertSame(70.0, $all['fixed']);
    }

    public function test_trend_returns_requested_number_of_buckets(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $beverages = Category::factory()->create(['name' => 'Beverages']);
        $this->seedScenario($user, $groceries, $beverages);

        $trend = (new ExpenseService)->trend(
            $user->id,
            CarbonImmutable::parse('2026-06-15'),
            'month',
            6,
        );

        $this->assertCount(6, $trend);
        $this->assertSame('Jun 2026', $trend[5]['label']);
        $this->assertSame(150.0, $trend[5]['fixed']);
        $this->assertSame(100.0, $trend[5]['variable']);
    }
}
