<?php

namespace Tests\Feature;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\PriceIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedMilkPrices(User $user): Product
    {
        $product = Product::factory()->for($user)->create([
            'name' => 'Milk 1L',
            'normalized_name' => 'milk-1l',
        ]);

        $rewe = Receipt::factory()->for($user)->create([
            'vendor' => 'REWE',
            'receipt_date' => '2026-06-10',
        ]);
        $reweItem = ReceiptItem::factory()->for($rewe)->create([
            'product_id' => $product->id,
            'name' => 'MILCH',
            'unit_price' => 1.50,
            'quantity' => 2,
        ]);
        PriceObservation::factory()->create([
            'product_id' => $product->id,
            'receipt_item_id' => $reweItem->id,
            'vendor' => 'REWE',
            'unit_price' => 1.50,
            'observed_at' => '2026-06-10',
        ]);

        $aldi = Receipt::factory()->for($user)->create([
            'vendor' => 'ALDI',
            'receipt_date' => '2026-06-05',
        ]);
        $aldiItem = ReceiptItem::factory()->for($aldi)->create([
            'product_id' => $product->id,
            'name' => 'MILCH',
            'unit_price' => 1.20,
            'quantity' => 1,
        ]);
        PriceObservation::factory()->create([
            'product_id' => $product->id,
            'receipt_item_id' => $aldiItem->id,
            'vendor' => 'ALDI',
            'unit_price' => 1.20,
            'observed_at' => '2026-06-05',
        ]);

        return $product;
    }

    public function test_savings_opportunities_surface_overpayments(): void
    {
        $user = User::factory()->create();
        $this->seedMilkPrices($user);

        $opportunities = (new PriceIntelligenceService)->savingsOpportunities($user->id);

        $this->assertCount(1, $opportunities);
        $this->assertSame('Milk 1L', $opportunities->first()->product_name);
        $this->assertSame('REWE', $opportunities->first()->vendor);
        $this->assertSame(1.20, (float) $opportunities->first()->cheapest_price);
        $this->assertSame('ALDI', $opportunities->first()->cheapest_vendor);
        $this->assertSame(0.60, (float) $opportunities->first()->potential_savings); // (1.50 - 1.20) * 2
    }

    public function test_cheapest_vendors_returns_lowest_price_per_product(): void
    {
        $user = User::factory()->create();
        $product = $this->seedMilkPrices($user);

        $cheapest = (new PriceIntelligenceService)->cheapestVendors($user->id);

        $this->assertCount(1, $cheapest);
        $this->assertSame($product->id, $cheapest->first()->product_id);
        $this->assertSame(1.20, (float) $cheapest->first()->cheapest_price);
        $this->assertSame('ALDI', $cheapest->first()->cheapest_vendor);
        $this->assertSame(2, (int) $cheapest->first()->vendor_count);
    }

    public function test_price_trends_detect_rising_and_falling_prices(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for($user)->create(['name' => 'Bread']);

        PriceObservation::factory()->for($product)->create([
            'unit_price' => 2.00,
            'observed_at' => '2026-06-01',
        ]);
        PriceObservation::factory()->for($product)->create([
            'unit_price' => 2.50,
            'observed_at' => '2026-06-15',
        ]);

        $trends = (new PriceIntelligenceService)->priceTrends($user->id);

        $this->assertCount(1, $trends);
        $this->assertSame('rising', $trends->first()->direction);
        $this->assertSame(0.50, (float) $trends->first()->change);
    }

    public function test_product_detail_is_scoped_to_user(): void
    {
        $user = User::factory()->create();
        $product = $this->seedMilkPrices($user);

        $detail = (new PriceIntelligenceService)->productDetail($user->id, $product);

        $this->assertSame($product->id, $detail['product']->id);
        $this->assertCount(2, $detail['price_history']);
        $this->assertCount(2, $detail['by_vendor']);
        $this->assertCount(2, $detail['purchases']);
    }
}
