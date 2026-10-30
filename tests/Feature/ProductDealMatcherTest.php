<?php

namespace Tests\Feature;

use App\Domain\Deals\Services\ProductDealMatcher;
use App\Domain\Products\Data\PurchasedProduct;
use App\Models\Category;
use App\Models\Deal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductDealMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, ?string $normalizedName = null, ?int $categoryId = null): PurchasedProduct
    {
        return new PurchasedProduct(1, $name, $normalizedName ?? $name, $categoryId, 3, 2.49, 'Rewe', '2026-10-01');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string, 3: bool}>
     */
    public static function cases(): array
    {
        return [
            'abbreviated receipt name' => ['BIO FREIL.EIER 10ST', 'Bio Freiland Eier', null, true],
            'single word inside compound' => ['H-Milch 3,5%', 'Frische Vollmilch 3,5%', null, true],
            'normalized name matches' => ['JAC.KROEN.500G', 'Jacobs Krönung Kaffee', 'jacobs-kronung', true],
            'umlaut spelled out vs folded' => ['Kase Gouda', 'Gouda Käse jung', null, true],
            'brand counts' => ['Milka Alpenmilch', 'Alpenmilch Schokolade', 'milka-alpenmilch', false],
            'different product' => ['Vollmilch', 'Bio Freiland Eier', null, false],
            'only filler words' => ['Bio 500g', 'Bio Freiland Eier', 'bio', false],
        ];
    }

    #[DataProvider('cases')]
    public function test_matching(string $productName, string $dealTitle, ?string $normalizedName, bool $expected): void
    {
        $deal = Deal::factory()->make(['title' => $dealTitle, 'brand' => null, 'category_id' => null]);

        $this->assertSame($expected, (new ProductDealMatcher)->matches($this->product($productName, $normalizedName), $deal));
    }

    public function test_offers_in_another_top_level_category_do_not_match(): void
    {
        $groceries = Category::factory()->create();
        $dairy = Category::factory()->childOf($groceries)->create();
        $household = Category::factory()->create();
        $electronics = Category::factory()->childOf($household)->create();

        $eggs = $this->product('Eier', categoryId: $dairy->id);
        $eggCooker = Deal::factory()->make(['title' => 'Eier Kocher', 'brand' => null, 'category_id' => $electronics->id]);
        $freshEggs = Deal::factory()->make(['title' => 'Freiland Eier', 'brand' => null, 'category_id' => $groceries->id]);

        $matcher = new ProductDealMatcher;

        $this->assertFalse($matcher->matches($eggs, $eggCooker));
        $this->assertTrue($matcher->matches($eggs, $freshEggs));
    }
}
