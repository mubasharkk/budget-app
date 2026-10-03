<?php

namespace Tests\Feature;

use App\Domain\Analytics\Services\ConsumptionService;
use App\Domain\Analytics\Services\ExpenseService;
use App\Domain\Assistant\Services\SpendingQueryExecutor;
use App\Domain\Budgets\Services\BudgetService;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_item_search_is_scoped_to_a_mentioned_category(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $electronics = Category::factory()->create(['name' => 'Electronics']);

        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-10']);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Battery pack', 'quantity' => 2, 'unit_price' => 5, 'category_id' => $groceries->id,
        ]);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Battery pack', 'quantity' => 9, 'unit_price' => 5, 'category_id' => $electronics->id,
        ]);

        $result = $this->executor()->execute($user->id, [
            'intent' => 'item_search',
            'item' => 'battery',
            'metric' => 'quantity',
            'category_id' => $groceries->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame(2.0, $result['items'][0]['quantity']); // electronics batteries excluded
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

    public function test_receipt_lookup_returns_items_and_is_scoped_to_owner(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create(['vendor' => 'ALDI']);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Bread', 'quantity' => 1, 'unit_price' => 2]);

        $result = $this->executor()->execute($user->id, [
            'intent' => 'receipt_lookup',
            'receipt_id' => $receipt->id,
        ]);

        $this->assertSame('ALDI', $result['receipt']['vendor']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Bread', $result['items'][0]['name']);

        // A stranger cannot read the same receipt.
        $stranger = User::factory()->create();
        $blocked = $this->executor()->execute($stranger->id, [
            'intent' => 'receipt_lookup',
            'receipt_id' => $receipt->id,
        ]);

        $this->assertNull($blocked['receipt']);
    }

    public function test_contract_lookup_returns_details(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->for($user)->create([
            'name' => 'Netflix', 'amount' => 12, 'billing_cycle' => 'monthly',
        ]);

        $result = $this->executor()->execute($user->id, [
            'intent' => 'contract_lookup',
            'contract_id' => $contract->id,
        ]);

        $this->assertSame('Netflix', $result['contract']['name']);
        $this->assertSame(12.0, $result['contract']['amount']);
        $this->assertSame('monthly', $result['contract']['billing_cycle']);
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

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'lookup intents cannot come from the LLM' => [['intent' => 'receipt_lookup'], 'not allowed'],
            'missing date range' => [['intent' => 'total_spend', 'start_date' => '2026-06-01'], 'date range'],
            'unknown category' => [['intent' => 'category_spend', 'category' => 'Yachts', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30'], 'Category not recognized'],
            'missing vendor' => [['intent' => 'vendor_spend', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30'], 'Vendor name'],
            'missing category term' => [['intent' => 'category_search'], 'category to search'],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    #[DataProvider('invalidQueries')]
    public function test_validation_rejects_unsafe_or_incomplete_queries(array $parsed, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->executor()->validateParsedQuery(1, $parsed);
    }

    public function test_validation_normalises_categories_metrics_and_default_ranges(): void
    {
        $this->travelTo('2026-06-15');
        Category::factory()->create(['name' => 'Home & Garden', 'slug' => 'home-garden']);
        $executor = $this->executor();
        $range = ['start_date' => '2026-06-01', 'end_date' => '2026-06-30'];

        $this->assertSame('Home & Garden', $executor->validateParsedQuery(1, ['intent' => 'category_spend', 'category' => 'Home Garden', ...$range])['category']);
        $this->assertSame('spend', $executor->validateParsedQuery(1, ['intent' => 'top_items', 'metric' => 'calories', ...$range])['metric']);
        $this->assertSame('quantity', $executor->validateParsedQuery(1, ['intent' => 'item_search', 'item' => 'milk', 'metric' => 'bogus', ...$range])['metric']);

        $budget = $executor->validateParsedQuery(1, ['intent' => 'budget_status']);
        $this->assertSame(['2026-06-01', '2026-06-30'], [$budget['start_date'], $budget['end_date']]);

        $search = $executor->validateParsedQuery(1, ['intent' => 'category_search', 'category' => 'garden']);
        $this->assertSame(['2026-01-01', '2026-12-31'], [$search['start_date'], $search['end_date']]);
    }

    public function test_total_vendor_top_items_and_budget_intents(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $receipt = Receipt::factory()->for($user)->create(['vendor' => 'REWE', 'receipt_date' => '2026-06-10', 'total_amount' => 12]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Milk', 'quantity' => 4, 'unit_price' => 1, 'category_id' => $groceries->id]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Cheese', 'quantity' => 1, 'unit_price' => 8, 'category_id' => $groceries->id]);
        $range = ['start_date' => '2026-06-01', 'end_date' => '2026-06-30'];
        $executor = $this->executor();

        $this->assertSame(12.0, (float) $executor->execute($user->id, ['intent' => 'total_spend', ...$range])['variable']);

        $vendor = $executor->execute($user->id, ['intent' => 'vendor_spend', 'vendor' => 'rewe', ...$range]);
        $this->assertSame([1, 12.0], [$vendor['receipt_count'], $vendor['total']]);
        $this->assertSame(0.0, $executor->execute($user->id, ['intent' => 'vendor_spend', 'vendor' => 'ALDI', ...$range])['total']);

        $byQuantity = $executor->execute($user->id, ['intent' => 'top_items', 'metric' => 'quantity', ...$range]);
        $this->assertSame('Milk', $byQuantity['items'][0]['name']);
        $bySpend = $executor->execute($user->id, ['intent' => 'top_items', 'metric' => 'spend', ...$range]);
        $this->assertSame('Cheese', $bySpend['items'][0]['name']);

        $budget = $executor->execute($user->id, ['intent' => 'budget_status']);
        $this->assertSame('budget_status', $budget['intent']);
        $this->assertSame([], $budget['items']);
    }

    public function test_lookups_of_missing_records_return_empty_results(): void
    {
        $user = User::factory()->create();
        $executor = $this->executor();

        $this->assertNull($executor->execute($user->id, ['intent' => 'receipt_lookup', 'receipt_id' => 999])['receipt']);
        $this->assertNull($executor->execute($user->id, ['intent' => 'contract_lookup', 'contract_id' => 999])['contract']);
    }
}
