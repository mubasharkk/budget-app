<?php

namespace Tests\Feature\Dashboard;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_spending_by_category_sums_item_totals_per_category(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $beverages = Category::factory()->create(['name' => 'Beverages']);

        $receipt = Receipt::factory()->for($user)->create();

        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 2, 'unit_price' => 10, 'category_id' => $groceries->id,
        ]); // total 20
        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 1, 'unit_price' => 5, 'category_id' => $groceries->id,
        ]); // total 5
        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 3, 'unit_price' => 4, 'category_id' => $beverages->id,
        ]); // total 12

        $spending = (new DashboardService)
            ->getSpendingByCategory($user->id)
            ->keyBy('category_name');

        $this->assertSame(25.0, (float) $spending['Groceries']->total_spent);
        $this->assertSame(12.0, (float) $spending['Beverages']->total_spent);
    }

    public function test_spending_by_category_is_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Groceries']);

        $ownReceipt = Receipt::factory()->for($user)->create();
        ReceiptItem::factory()->for($ownReceipt)->create([
            'quantity' => 1, 'unit_price' => 10, 'category_id' => $category->id,
        ]);

        $otherReceipt = Receipt::factory()->for($other)->create();
        ReceiptItem::factory()->for($otherReceipt)->create([
            'quantity' => 1, 'unit_price' => 999, 'category_id' => $category->id,
        ]);

        $spending = (new DashboardService)
            ->getSpendingByCategory($user->id)
            ->keyBy('category_name');

        $this->assertSame(10.0, (float) $spending['Groceries']->total_spent);
    }
}
