<?php

namespace Tests\Feature;

use App\Models\Category;
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
