<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ReceiptKind;
use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $groceries;

    private Category $dairy;

    private Category $drinks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->groceries = Category::factory()->create(['name' => 'Groceries']);
        $this->dairy = Category::factory()->childOf($this->groceries)->create(['name' => 'Dairy']);
        $this->drinks = Category::factory()->create(['name' => 'Drinks']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{0: string, 1: int, 2: float, 3: Category}>  $items  name, quantity, unit price, category
     */
    private function receipt(array $attributes, array $items = []): Receipt
    {
        $receipt = Receipt::factory()->for($this->user)->create($attributes);

        foreach ($items as [$name, $quantity, $unitPrice, $category]) {
            ReceiptItem::factory()->for($receipt)->create([
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'category_id' => $category->id,
            ]);
        }

        return $receipt;
    }

    private function seedReceipts(): void
    {
        $june = $this->receipt(['total_amount' => 30, 'created_at' => '2026-06-10 12:00:00'], [
            ['Milk', 3, 1.0, $this->dairy],
            ['Bread', 1, 2.0, $this->groceries],
            ['Cola', 2, 1.5, $this->drinks],
        ]);
        $this->receipt(['total_amount' => 10, 'created_at' => '2026-05-02 12:00:00'], [['Milk', 5, 1.0, $this->dairy]]);
        $this->receipt(['total_amount' => 30, 'created_at' => '2026-06-11 12:00:00', 'duplicate_of_id' => $june->id], [['Milk', 9, 1.0, $this->dairy]]);
        $this->receipt(['total_amount' => 2000, 'created_at' => '2026-06-12 12:00:00', 'kind' => ReceiptKind::Income]);
        Receipt::factory()->create(['total_amount' => 99, 'created_at' => '2026-06-10 12:00:00']);
    }

    public function test_most_bought_items_respect_dates_category_tree_and_duplicates(): void
    {
        $this->seedReceipts();
        $this->actingAs($this->user);

        $this->getJson('/dashboard/chart/data?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.0', ['item' => 'Milk', 'category' => 'Dairy', 'quantity' => 3])
            ->assertJsonCount(3, 'data');

        $groceryItems = collect($this->getJson("/dashboard/chart/data?category_id={$this->groceries->id}")->json('data'));
        $this->assertEqualsCanonicalizing(['Milk', 'Bread'], $groceryItems->pluck('item')->all());
        $this->assertSame(8, $groceryItems->firstWhere('item', 'Milk')['quantity']);

        $this->getJson("/dashboard/chart/data?category_id={$this->dairy->id}")
            ->assertJsonPath('data', [['item' => 'Milk', 'category' => 'Dairy', 'quantity' => 8]])
            ->assertJsonPath('categories', ['Dairy']);
    }

    public function test_stats_count_only_the_users_unique_expense_receipts(): void
    {
        $this->seedReceipts();

        $stats = $this->actingAs($this->user)
            ->getJson('/dashboard/stats?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()
            ->json('stats');

        $this->assertSame(1, $stats['total_receipts']);
        $this->assertEquals(30, $stats['total_spent']);
        $this->assertEquals(30, $stats['average_receipt_value']);
        $this->assertEquals(6, $stats['total_items']);
    }

    public function test_spending_by_category_filters_by_upload_date(): void
    {
        $this->seedReceipts();

        $spending = collect($this->actingAs($this->user)
            ->getJson('/dashboard/spending-by-category?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()
            ->json('data'))
            ->pluck('total_spent', 'category_name');

        $this->assertEquals(3, $spending['Dairy']);
        $this->assertEquals(3, $spending['Drinks']);
        $this->assertEquals(2, $spending['Groceries']);
    }

    public function test_category_filter_lists_parents_followed_by_their_subcategories(): void
    {
        $this->actingAs($this->user)
            ->getJson('/dashboard/categories')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->groceries->id, 'type' => 'parent', 'subcategories' => 1])
            ->assertJsonFragment(['id' => $this->dairy->id, 'name' => '  └ Dairy', 'parent_id' => $this->groceries->id])
            ->assertJsonFragment(['id' => $this->drinks->id, 'type' => 'parent', 'subcategories' => 0]);
    }

    public function test_remaining_dashboard_endpoints_respond(): void
    {
        $this->seedReceipts();
        $this->actingAs($this->user);

        foreach (['overview', 'trend', 'consumption', 'deals', 'budgets', 'snapshot'] as $endpoint) {
            $this->getJson("/dashboard/{$endpoint}")->assertOk();
        }

        $this->get('/dashboard')->assertOk();
    }
}
