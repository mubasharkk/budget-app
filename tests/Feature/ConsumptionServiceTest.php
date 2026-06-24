<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\ConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(User $user): void
    {
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $rewe = Receipt::factory()->for($user)->create(['vendor' => 'REWE', 'total_amount' => 50]);
        ReceiptItem::factory()->for($rewe)->create(['name' => 'Milk', 'quantity' => 5, 'unit_price' => 1, 'category_id' => $groceries->id]); // qty 5, spend 5
        ReceiptItem::factory()->for($rewe)->create(['name' => 'Steak', 'quantity' => 1, 'unit_price' => 30, 'category_id' => $groceries->id]); // qty 1, spend 30

        $aldi = Receipt::factory()->for($user)->create(['vendor' => 'ALDI', 'total_amount' => 20]);
        ReceiptItem::factory()->for($aldi)->create(['name' => 'Milk', 'quantity' => 3, 'unit_price' => 1, 'category_id' => $groceries->id]); // qty 3, spend 3

        // Another user's data must be excluded.
        $other = Receipt::factory()->create(['vendor' => 'LIDL', 'total_amount' => 500]);
        ReceiptItem::factory()->for($other)->create(['name' => 'Milk', 'quantity' => 99, 'unit_price' => 1]);
    }

    public function test_monthly_spend_trend_totals_by_month_and_filters_by_category(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $beverages = Category::factory()->create(['name' => 'Beverages']);

        $march = Receipt::factory()->for($user)->create(['receipt_date' => '2026-03-10']);
        ReceiptItem::factory()->for($march)->create(['quantity' => 1, 'unit_price' => 40, 'category_id' => $groceries->id]);
        ReceiptItem::factory()->for($march)->create(['quantity' => 1, 'unit_price' => 10, 'category_id' => $beverages->id]);

        $july = Receipt::factory()->for($user)->create(['receipt_date' => '2026-07-05']);
        ReceiptItem::factory()->for($july)->create(['quantity' => 1, 'unit_price' => 25, 'category_id' => $groceries->id]);

        // Different year must be excluded.
        $prevYear = Receipt::factory()->for($user)->create(['receipt_date' => '2025-03-01']);
        ReceiptItem::factory()->for($prevYear)->create(['quantity' => 1, 'unit_price' => 999, 'category_id' => $groceries->id]);

        $trend = (new ConsumptionService)->monthlySpendTrend($user->id, 2026);

        $this->assertCount(12, $trend);
        $this->assertSame(3, $trend[2]['month']);
        $this->assertSame(50.0, $trend[2]['total']); // 40 + 10
        $this->assertSame(25.0, $trend[6]['total']); // July
        $this->assertSame(0.0, $trend[0]['total']);  // January is zero-filled

        $groceriesTrend = (new ConsumptionService)->monthlySpendTrend($user->id, 2026, $groceries->id);
        $this->assertSame(40.0, $groceriesTrend[2]['total']); // beverages excluded
        $this->assertSame(25.0, $groceriesTrend[6]['total']);
    }

    public function test_monthly_contract_trend_respects_billing_window_and_category(): void
    {
        $user = User::factory()->create();
        $utilities = Category::factory()->create(['name' => 'Utilities']);
        $other = Category::factory()->create(['name' => 'Other']);

        // Monthly contract active from April onward → 100/mo from April..December.
        Contract::factory()->for($user)->create([
            'amount' => 100,
            'billing_cycle' => 'monthly',
            'start_date' => '2026-04-01',
            'end_date' => null,
            'category_id' => $utilities->id,
        ]);

        // Different category — excluded when filtering on Utilities.
        Contract::factory()->for($user)->create([
            'amount' => 50,
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'end_date' => null,
            'category_id' => $other->id,
        ]);

        $trend = (new ConsumptionService)->monthlyContractTrend($user->id, 2026, $utilities->id);

        $this->assertCount(12, $trend);
        $this->assertSame(0.0, $trend[2]['total']);   // March — before start
        $this->assertSame(100.0, $trend[3]['total']); // April — active
        $this->assertSame(100.0, $trend[11]['total']); // December — still active
    }

    public function test_monthly_contract_series_splits_by_category_when_unfiltered(): void
    {
        $user = User::factory()->create();
        $utilities = Category::factory()->create(['name' => 'Utilities']);
        $internet = Category::factory()->create(['name' => 'Internet']);

        Contract::factory()->for($user)->create([
            'amount' => 100, 'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01', 'end_date' => null, 'category_id' => $utilities->id,
        ]);
        Contract::factory()->for($user)->create([
            'amount' => 30, 'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01', 'end_date' => null, 'category_id' => $internet->id,
        ]);

        $series = (new ConsumptionService)->monthlyContractSeries($user->id, 2026);

        $this->assertCount(2, $series);
        $utilitiesSeries = collect($series)->firstWhere('label', 'Utilities');
        $this->assertNotNull($utilitiesSeries);
        $this->assertCount(12, $utilitiesSeries['monthly']);
        $this->assertSame(100.0, $utilitiesSeries['monthly'][1]);

        // A single selected category collapses to one combined series.
        $single = (new ConsumptionService)->monthlyContractSeries($user->id, 2026, $utilities->id);
        $this->assertCount(1, $single);
        $this->assertSame('contracts', $single[0]['key']);
    }

    public function test_top_items_rank_differently_by_quantity_and_spend(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);

        $byQuantity = (new ConsumptionService)->topItems($user->id, 'quantity');
        $this->assertSame('Milk', $byQuantity->first()->item_name);
        $this->assertSame(8.0, (float) $byQuantity->first()->total_quantity); // 5 + 3

        $bySpend = (new ConsumptionService)->topItems($user->id, 'spend');
        $this->assertSame('Steak', $bySpend->first()->item_name);
        $this->assertSame(30.0, (float) $bySpend->first()->total_spend);
    }

    public function test_vendor_leaderboard_ranks_by_total_spend_and_is_scoped(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);

        $vendors = (new ConsumptionService)->vendorLeaderboard($user->id);

        $this->assertCount(2, $vendors); // other user's LIDL excluded
        $this->assertSame('REWE', $vendors->first()->vendor);
        $this->assertSame(50.0, (float) $vendors->first()->total_spent);
        $this->assertSame(1, (int) $vendors->first()->receipt_count);
    }

    public function test_date_range_filters_items(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $old = Receipt::factory()->for($user)->create(['receipt_date' => '2026-01-01']);
        ReceiptItem::factory()->for($old)->create(['name' => 'OldThing', 'quantity' => 10, 'unit_price' => 1, 'category_id' => $groceries->id]);

        $recent = Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-15']);
        ReceiptItem::factory()->for($recent)->create(['name' => 'NewThing', 'quantity' => 1, 'unit_price' => 1, 'category_id' => $groceries->id]);

        $items = (new ConsumptionService)->topItems($user->id, 'quantity', '2026-06-01', '2026-06-30');

        $this->assertCount(1, $items);
        $this->assertSame('NewThing', $items->first()->item_name);
    }
}
