<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\ConsumptionService;
use App\Services\ExpenseService;
use App\Services\SpendingQueryExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendingQueryExecutorTest extends TestCase
{
    use RefreshDatabase;

    private function executor(): SpendingQueryExecutor
    {
        return new SpendingQueryExecutor(
            app(ExpenseService::class),
            app(ConsumptionService::class),
            app(BudgetService::class),
        );
    }

    public function test_category_spend_returns_fixed_and_variable(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-10']);
        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 1, 'unit_price' => 75, 'category_id' => $groceries->id,
        ]);

        $result = (new SpendingQueryExecutor(
            app(ExpenseService::class),
            app(ConsumptionService::class),
            app(BudgetService::class),
        ))->execute($user->id, [
            'intent' => 'category_spend',
            'category' => 'Groceries',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertSame('category_spend', $result['intent']);
        $this->assertSame(75.0, $result['total']);
    }

    public function test_item_search_returns_quantity_and_spend(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-10']);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Bio Eggs 10-pack', 'quantity' => 3, 'unit_price' => 3, 'category_id' => $groceries->id,
        ]);

        $result = $this->executor()->execute($user->id, [
            'intent' => 'item_search',
            'item' => 'egg',
            'metric' => 'quantity',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertSame('item_search', $result['intent']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Bio Eggs 10-pack', $result['items'][0]['name']);
        $this->assertSame(3.0, $result['items'][0]['quantity']);
        $this->assertSame(9.0, $result['items'][0]['spend']);
    }

    public function test_item_search_requires_an_item_term(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->executor()->validateParsedQuery($user->id, [
            'intent' => 'item_search',
            'item' => '',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);
    }

    public function test_category_search_rolls_up_matching_categories(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $receipt = Receipt::factory()->for($user)->create();
        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 1, 'unit_price' => 40, 'category_id' => $groceries->id,
        ]);

        $result = $this->executor()->execute($user->id, [
            'intent' => 'category_search',
            'category' => 'grocer',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->assertSame('category_search', $result['intent']);
        $this->assertSame('Groceries', $result['categories'][0]['category']);
        $this->assertSame(40.0, $result['categories'][0]['spend']);
    }

    public function test_rejects_unknown_intent(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        (new SpendingQueryExecutor(
            app(ExpenseService::class),
            app(ConsumptionService::class),
            app(BudgetService::class),
        ))->execute($user->id, [
            'intent' => 'drop_table',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);
    }
}
