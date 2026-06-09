<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\SpendingQueryExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendingQueryExecutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_spend_returns_fixed_and_variable(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-10']);
        ReceiptItem::factory()->for($receipt)->create([
            'quantity' => 1, 'unit_price' => 75, 'category_id' => $groceries->id,
        ]);

        $result = (new SpendingQueryExecutor(
            app(\App\Services\ExpenseService::class),
            app(\App\Services\ConsumptionService::class),
            app(\App\Services\BudgetService::class),
        ))->execute($user->id, [
            'intent' => 'category_spend',
            'category' => 'Groceries',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertSame('category_spend', $result['intent']);
        $this->assertSame(75.0, $result['total']);
    }

    public function test_rejects_unknown_intent(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        (new SpendingQueryExecutor(
            app(\App\Services\ExpenseService::class),
            app(\App\Services\ConsumptionService::class),
            app(\App\Services\BudgetService::class),
        ))->execute($user->id, [
            'intent' => 'drop_table',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);
    }
}
