<?php

namespace Tests\Feature;

use App\Enums\Retailer;
use App\Models\Deal;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealsForYouTest extends TestCase
{
    use RefreshDatabase;

    private function buys(User $user, string $name, float $lastPaid, int $times = 1, string $vendor = 'Rewe', ?string $unit = null, ?string $size = null, string $currency = 'EUR'): Product
    {
        $product = Product::factory()->for($user)->create(['name' => $name, 'normalized_name' => (string) str($name)->slug(), 'category_id' => null, 'unit' => $unit, 'size' => $size]);

        PriceObservation::factory()->for($product)->count($times)->create([
            'unit_price' => $lastPaid,
            'vendor' => $vendor,
            'observed_at' => now()->subDays(3),
        ]);

        for ($purchase = 0; $purchase < $times; $purchase++) {
            ReceiptItem::factory()
                ->for(Receipt::factory()->for($user)->create(['receipt_date' => now()->subDays(3 + $purchase), 'vendor' => $vendor, 'currency' => $currency]))
                ->create(['name' => $name, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => $lastPaid]);
        }

        return $product;
    }

    private function offer(string $title, float $price, Retailer $retailer = Retailer::Lidl, string $postalCode = '10115', ?float $unitPrice = null, ?string $unit = null): Deal
    {
        return Deal::factory()->availableIn($postalCode)->create([
            'title' => $title,
            'brand' => null,
            'price' => $price,
            'retailer' => $retailer,
            'unit_price' => $unitPrice,
            'unit_price_unit' => $unit,
        ]);
    }

    public function test_without_a_postal_code_the_user_is_asked_for_one(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('deals.for-you'))
            ->assertStatus(409)
            ->assertJson(['postal_code_required' => true]);
    }

    public function test_offers_on_products_the_user_buys_are_recommended_with_the_saving(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();
        $this->buys($user, 'Freiland Eier', 2.50, times: 3, unit: 'pcs', size: '10');
        $eggs = $this->offer('Freiland Eier', 1.80, unitPrice: 0.18, unit: 'Stück');
        $this->offer('Kaffee', 4.99);

        $response = $this->actingAs($user)->getJson(route('deals.for-you'))->assertOk();

        $response->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.offer.id', $eggs->id)
            ->assertJsonPath('recommendations.0.product.name', 'Freiland Eier')
            ->assertJsonPath('recommendations.0.product.purchase_count', 3)
            ->assertJsonPath('recommendations.0.product.last_vendor', 'Rewe')
            ->assertJsonPath('recommendations.0.saving', 0.7)
            ->assertJsonPath('summary', [
                'match_count' => 1,
                'possible_saving' => 0.7,
                'best_retailer' => 'lidl',
                'best_retailer_label' => 'Lidl',
            ]);
    }

    public function test_a_smaller_pack_is_not_a_saving(): void
    {
        $user = User::factory()->withPostalCode('10115')->create(['default_currency' => 'EUR']);
        $this->buys($user, 'Kaffee', 5.00, times: 2, unit: 'kg', size: '0.5');
        $this->offer('Kaffee', 3.00, unitPrice: 12.00, unit: 'kg');

        $this->actingAs($user)->getJson(route('deals.for-you'))
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.saving', null)
            ->assertJsonPath('summary.possible_saving', 0);
    }

    public function test_a_bigger_pack_saves_what_insights_says_it_saves(): void
    {
        $user = User::factory()->withPostalCode('10115')->create(['default_currency' => 'EUR']);
        $this->buys($user, 'Kaffee', 5.00, times: 2, unit: 'kg', size: '0.5');
        $this->offer('Kaffee Crema', 3.50, unitPrice: 14.00, unit: 'kg');
        $bigPack = $this->offer('Kaffee', 8.00, unitPrice: 8.00, unit: 'kg');

        $this->actingAs($user)->getJson(route('deals.for-you'))
            ->assertJsonPath('recommendations.0.offer.id', $bigPack->id)
            ->assertJsonPath('recommendations.0.saving', 1);
        $this->getJson(route('insights.next-steps'))
            ->assertJsonPath('savings.rows.0.offer.id', $bigPack->id)
            ->assertJsonPath('savings.rows.0.saving', 1);
    }

    public function test_a_product_named_on_insights_without_price_history_still_gets_its_saving(): void
    {
        $user = User::factory()->withPostalCode('10115')->create(['default_currency' => 'EUR']);
        $product = $this->buys($user, 'Kaffee', 5.00, times: 2, unit: 'kg', size: '0.5');
        $product->priceObservations()->delete();
        $this->offer('Kaffee', 4.00, unitPrice: 8.00, unit: 'kg');

        $this->actingAs($user)->getJson(route('deals.for-you'))
            ->assertJsonPath('recommendations.0.product.purchase_count', 2)
            ->assertJsonPath('recommendations.0.saving', 1);
    }

    public function test_euro_offers_are_not_a_saving_for_someone_counting_in_another_currency(): void
    {
        $user = User::factory()->withPostalCode('10115')->create(['default_currency' => 'USD']);
        $this->buys($user, 'Kaffee', 5.00, times: 2, unit: 'kg', size: '0.5', currency: 'USD');
        $this->offer('Kaffee', 4.00, unitPrice: 8.00, unit: 'kg');

        $this->actingAs($user)->getJson(route('deals.for-you'))
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.saving', null);
    }

    public function test_recommendations_are_ranked_by_saving_then_by_how_often_the_product_is_bought(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();
        $this->buys($user, 'Milch', 1.19, times: 9, unit: 'l', size: '1');
        $this->buys($user, 'Butter', 2.50, times: 2, unit: 'kg', size: '0.25');
        $this->buys($user, 'Joghurt', 0.89, times: 4);
        $milk = $this->offer('Frische Milch', 0.99, unitPrice: 0.99, unit: 'l');
        $butter = $this->offer('Deutsche Butter', 1.80, unitPrice: 7.20, unit: 'kg');
        $yoghurt = $this->offer('Joghurt mild', 0.99);

        $ids = collect($this->actingAs($user)->getJson(route('deals.for-you'))->json('recommendations'))->pluck('offer.id')->all();

        $this->assertSame([$butter->id, $milk->id, $yoghurt->id], $ids);
    }

    public function test_an_offer_that_is_not_cheaper_is_shown_without_a_saving(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();
        $this->buys($user, 'Milch', 0.89);
        $this->offer('Vollmilch', 0.99);

        $this->actingAs($user)->getJson(route('deals.for-you'))
            ->assertJsonPath('recommendations.0.saving', null)
            ->assertJsonPath('summary.possible_saving', 0);
    }

    public function test_each_offer_is_recommended_only_once(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();
        $this->buys($user, 'Milch', 1.19, times: 5);
        $this->buys($user, 'Vollmilch', 1.29, times: 2);
        $this->offer('Frische Vollmilch', 0.99);

        $this->actingAs($user)->getJson(route('deals.for-you'))->assertJsonCount(1, 'recommendations');
    }

    public function test_the_cheapest_matching_offer_wins_and_the_best_store_has_most_matches(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();
        $this->buys($user, 'Milch', 1.19);
        $this->buys($user, 'Butter', 2.49);
        $this->buys($user, 'Kaffee', 7.99);
        $this->offer('Vollmilch', 1.09, Retailer::Lidl);
        $cheapestMilk = $this->offer('Haltbare Milch', 0.89, Retailer::Aldi);
        $this->offer('Markenbutter', 1.99, Retailer::Aldi);
        $this->offer('Kaffee Crema', 5.99, Retailer::Kaufland);

        $response = $this->actingAs($user)->getJson(route('deals.for-you'));

        $this->assertContains($cheapestMilk->id, collect($response->json('recommendations'))->pluck('offer.id'));
        $response->assertJsonPath('summary.best_retailer', 'aldi');
    }

    public function test_other_users_products_and_other_postal_codes_are_ignored(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();
        $this->buys(User::factory()->create(), 'Milch', 1.19);
        $this->buys($user, 'Butter', 2.49);
        $this->offer('Vollmilch', 0.99);
        $this->offer('Markenbutter', 1.79, postalCode: '80331');

        $this->actingAs($user)->getJson(route('deals.for-you'))->assertJsonCount(0, 'recommendations');
    }

    public function test_expired_offers_are_not_recommended(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();
        $this->buys($user, 'Milch', 1.19);
        Deal::factory()->availableIn('10115')->expired()->create(['title' => 'Vollmilch', 'price' => 0.99]);

        $this->actingAs($user)->getJson(route('deals.for-you'))->assertJsonCount(0, 'recommendations');
    }

    public function test_the_limit_caps_the_list_but_not_the_summary(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();

        foreach (['Milch', 'Butter', 'Joghurt'] as $name) {
            $this->buys($user, $name, 3.00, times: 2, unit: 'kg', size: '1');
            $this->offer($name, 1.60, unitPrice: 1.60, unit: 'kg');
        }

        $this->actingAs($user)->getJson(route('deals.for-you', ['limit' => 2]))
            ->assertJsonCount(2, 'recommendations')
            ->assertJsonPath('summary.match_count', 3)
            ->assertJsonPath('summary.possible_saving', 4.2);
    }

    public function test_a_user_without_purchases_gets_an_empty_summary(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();
        $this->offer('Vollmilch', 0.99);

        $this->actingAs($user)->getJson(route('deals.for-you'))
            ->assertJsonCount(0, 'recommendations')
            ->assertJsonPath('summary', ['match_count' => 0, 'possible_saving' => 0, 'best_retailer' => null, 'best_retailer_label' => null]);
    }

    public function test_an_invalid_limit_is_rejected(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();

        $this->actingAs($user)->getJson(route('deals.for-you', ['limit' => 100]))->assertJsonValidationErrors('limit');
    }
}
