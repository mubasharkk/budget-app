<?php

namespace Tests\Feature;

use App\Enums\BudgetPeriod;
use App\Models\Budget;
use App\Models\Category;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\RecommendationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_combines_savings_and_budget_alerts(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $product = Product::factory()->for($user)->create(['name' => 'Milk']);
        $receipt = Receipt::factory()->for($user)->create(['vendor' => 'REWE', 'receipt_date' => '2026-06-10']);
        $item = ReceiptItem::factory()->for($receipt)->create([
            'product_id' => $product->id,
            'unit_price' => 2.00,
            'quantity' => 1,
        ]);
        PriceObservation::factory()->create([
            'product_id' => $product->id,
            'receipt_item_id' => $item->id,
            'vendor' => 'REWE',
            'unit_price' => 2.00,
            'observed_at' => '2026-06-10',
        ]);
        PriceObservation::factory()->for($product)->create([
            'vendor' => 'ALDI',
            'unit_price' => 1.20,
            'observed_at' => '2026-06-05',
        ]);

        $overReceipt = Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-12']);
        ReceiptItem::factory()->for($overReceipt)->create([
            'quantity' => 1, 'unit_price' => 200, 'category_id' => $groceries->id,
        ]);

        Budget::factory()->for($user)->create([
            'category_id' => $groceries->id,
            'amount' => 100,
            'period' => BudgetPeriod::Monthly,
            'starts_on' => '2026-06-01',
        ]);

        $recommendations = (new RecommendationService(
            app(\App\Services\PriceIntelligenceService::class),
            app(\App\Services\BudgetService::class),
        ))->recommendations($user->id);

        $types = collect($recommendations)->pluck('type')->unique()->all();
        $this->assertContains('savings', $types);
        $this->assertContains('budget', $types);

        CarbonImmutable::setTestNow();
    }
}
