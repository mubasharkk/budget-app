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
        $lidl = $this->offer('Kaffee 1kg', Retailer::Lidl, 9.99, 9.99, 'kg');
        $this->offer('Kaffee gemahlen 1kg', Retailer::Lidl, 12.99, 12.99, 'kg');
        $aldi = $this->offer('Kaffee 500g', Retailer::Aldi, 4.49, 8.98, 'kg');

        $comparison = $this->comparisons()->sole();

        $this->assertSame([$aldi->id, $lidl->id], $comparison->deals->pluck('id')->all());
        $this->assertSame(1.01, $comparison->priceGap());
    }

    public function test_a_smaller_pack_with_a_lower_pack_price_is_not_called_cheapest(): void
    {
        $this->buys('Kaffee');
        $kaufland = $this->offer('Kaffee 1kg', Retailer::Kaufland, 11.98, 11.98, 'kg');
        $lidl = $this->offer('Kaffee 250g', Retailer::Lidl, 3.49, 13.96, 'kg');

        $comparison = $this->comparisons()->sole();

        $this->assertSame([$kaufland->id, $lidl->id], $comparison->deals->pluck('id')->all());
        $this->assertSame(1.98, $comparison->priceGap());
    }

    public function test_offers_without_a_unit_price_are_never_compared(): void
    {
        $this->buys('Kaffee');
        $this->offer('Kaffee 500g', Retailer::Kaufland, 5.99, 11.98, 'kg');
        $this->offer('Kaffee', Retailer::Lidl, 3.49);

        $this->assertCount(0, $this->comparisons());
    }

    public function test_offers_in_different_units_are_compared_only_within_the_unit_most_retailers_share(): void
    {
        $this->buys('Kaffee');
        $aldi = $this->offer('Kaffee 500g', Retailer::Aldi, 4.49, 8.98, 'kg');
        $lidl = $this->offer('Kaffee 1kg', Retailer::Lidl, 9.99, 9.99, 'kg');
        $this->offer('Kaffee 10 Stück', Retailer::Kaufland, 2.99, 0.30, 'Stück');

        $comparison = $this->comparisons()->sole();

        $this->assertSame([$aldi->id, $lidl->id], $comparison->deals->pluck('id')->all());
        $this->assertSame(1.01, $comparison->priceGap());
    }

    public function test_offers_for_a_different_product_with_a_similar_name_are_not_compared(): void
    {
        $this->buys('Milch');
        $aldi = $this->offer('Milch', Retailer::Aldi, 0.99, 0.99, 'l');
        $this->offer('Buttermilch', Retailer::Lidl, 0.59, 1.18, 'l');
        $this->offer('Milchreis', Retailer::Kaufland, 0.79, 0.79, 'kg');

        $this->assertCount(0, $this->comparisons());

        $lidl = $this->offer('Frische Milch', Retailer::Lidl, 1.09, 1.09, 'l');

        $this->assertSame([$aldi->id, $lidl->id], $this->comparisons()->sole()->deals->pluck('id')->all());
    }

    public function test_products_offered_by_a_single_retailer_are_not_compared(): void
    {
        $this->buys('Butter');
        $this->offer('Butter', Retailer::Aldi, 1.99);
        $this->offer('Deutsche Butter', Retailer::Aldi, 2.29);

        $this->assertCount(0, $this->comparisons());
    }

    public function test_the_biggest_price_gap_comes_first(): void
    {
        $this->buys('Milch', times: 5);
        $this->buys('Kaffee');
        $this->offer('Milch', Retailer::Aldi, 0.99, 0.99, 'l');
        $this->offer('Frische Milch', Retailer::Lidl, 1.09, 1.09, 'l');
        $this->offer('Kaffee 500g', Retailer::Aldi, 4.99, 9.98, 'kg');
        $this->offer('Kaffee gemahlen 500g', Retailer::Kaufland, 7.99, 15.98, 'kg');

        $this->assertSame(['Kaffee', 'Milch'], $this->comparisons()->map(fn (DealComparison $comparison) => $comparison->product->name)->all());
    }
}
