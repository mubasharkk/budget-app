<?php

namespace Tests\Feature;

use App\Domain\Assistant\Services\RecommendationService;
use App\Domain\Budgets\Services\BudgetService;
use App\Domain\Products\Services\PriceIntelligenceService;
use App\Enums\BudgetPeriod;
use App\Models\Budget;
use App\Models\Category;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
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
            app(PriceIntelligenceService::class),
            app(BudgetService::class),
        ))->recommendations($user->id);

        $types = collect($recommendations)->pluck('type')->unique()->all();
        $this->assertContains('savings', $types);
        $this->assertContains('budget', $types);

        CarbonImmutable::setTestNow();
    }

    public function test_budget_rows_become_ranked_alerts_in_the_users_currency(): void
    {
        $user = User::factory()->create(['default_currency' => 'USD']);
        $row = fn (string $label, string $status, string $projectedStatus): array => [
            'budget_id' => crc32($label),
            'label' => $label,
            'status' => $status,
            'projected_status' => $projectedStatus,
            'actual' => 90.0,
            'budget_amount' => 100.0,
            'remaining' => 10.0,
            'percent_used' => 90.0,
            'projected' => 150.0,
            'projected_percent' => 150.0,
        ];

        $budgets = $this->mock(BudgetService::class);
        $budgets->shouldReceive('summary')->andReturn(['items' => [
            $row('Dining', 'warning', 'over'),
            $row('Fuel', 'ok', 'over'),
            $row('Rent', 'ok', 'ok'),
            $row('Groceries', 'over', 'over'),
        ]]);
        $prices = $this->mock(PriceIntelligenceService::class);
        $prices->shouldReceive('savingsOpportunities')->andReturn(collect());

        $recommendations = (new RecommendationService($prices, $budgets))->recommendations($user->id);

        $this->assertSame(
            ['Over budget: Groceries', 'Projected overspend: Fuel', 'Near budget limit: Dining'],
            array_column($recommendations, 'title'),
        );
        $this->assertSame('At 90% of your $100.00 budget with $10.00 remaining.', $recommendations[2]['description']);
        $this->assertCount(1, (new RecommendationService($prices, $budgets))->recommendations($user->id, limit: 1));
    }
}
