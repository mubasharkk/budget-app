<?php

namespace Tests\Feature;

use App\Domain\Deals\Data\DealComparison;
use App\Domain\Deals\Services\DealComparisonService;
use App\Enums\Retailer;
use App\Models\Deal;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DealComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPostalCode('10115')->create();
    }

    private function buys(string $name, int $times = 1): void
    {
        $product = Product::factory()->for($this->user)->create(['name' => $name, 'normalized_name' => (string) str($name)->slug(), 'category_id' => null]);
        PriceObservation::factory()->for($product)->count($times)->create(['unit_price' => 2.00, 'observed_at' => now()->subDays(3)]);
    }

    private function offer(string $title, Retailer $retailer, float $price, ?float $unitPrice = null, ?string $unit = null): Deal
    {
        return Deal::factory()->availableIn('10115')->create([
            'title' => $title,
            'brand' => null,
            'retailer' => $retailer,
            'price' => $price,
            'unit_price' => $unitPrice,
            'unit_price_unit' => $unit,
        ]);
    }

    /**
     * @return Collection<int, DealComparison>
     */
    private function comparisons(): Collection
    {
        return app(DealComparisonService::class)->forUser($this->user->id, '10115');
    }

    public function test_a_product_on_offer_at_several_retailers_is_compared_by_unit_price_cheapest_first(): void
    {
        $this->buys('Kaffee');
        $lidl = $this->offer('Kaffee Crema 1kg', Retailer::Lidl, 9.99, 9.99, 'kg');
        $this->offer('Kaffee Bohnen 1kg', Retailer::Lidl, 12.99, 12.99, 'kg');
        $aldi = $this->offer('Kaffee 500g', Retailer::Aldi, 4.49, 8.98, 'kg');

        $comparison = $this->comparisons()->sole();

        $this->assertTrue($comparison->comparesUnitPrices);
        $this->assertSame([$aldi->id, $lidl->id], $comparison->deals->pluck('id')->all());
        $this->assertSame(1.01, $comparison->priceGap());
    }

    public function test_offers_without_a_shared_unit_are_compared_by_pack_price(): void
    {
        $this->buys('Kaffee');
        $kaufland = $this->offer('Kaffee Crema', Retailer::Kaufland, 5.99, 11.98, 'kg');
        $lidl = $this->offer('Kaffee Pads', Retailer::Lidl, 3.49);

        $comparison = $this->comparisons()->sole();

        $this->assertFalse($comparison->comparesUnitPrices);
        $this->assertSame([$lidl->id, $kaufland->id], $comparison->deals->pluck('id')->all());
        $this->assertSame(2.5, $comparison->priceGap());
    }

    public function test_products_offered_by_a_single_retailer_are_not_compared(): void
    {
        $this->buys('Butter');
        $this->offer('Markenbutter', Retailer::Aldi, 1.99);
        $this->offer('Süßrahmbutter', Retailer::Aldi, 2.29);

        $this->assertCount(0, $this->comparisons());
    }

    public function test_the_biggest_price_gap_comes_first(): void
    {
        $this->buys('Milch', times: 5);
        $this->buys('Kaffee');
        $this->offer('Vollmilch', Retailer::Aldi, 0.99);
        $this->offer('Frische Milch', Retailer::Lidl, 1.09);
        $this->offer('Kaffee Crema', Retailer::Aldi, 4.99);
        $this->offer('Kaffee Gold', Retailer::Kaufland, 7.99);

        $this->assertSame(['Kaffee', 'Milch'], $this->comparisons()->map(fn (DealComparison $comparison) => $comparison->product->name)->all());
    }
}
