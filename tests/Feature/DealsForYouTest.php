<?php

namespace Tests\Feature;

use App\Enums\Retailer;
use App\Models\Deal;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealsForYouTest extends TestCase
{
    use RefreshDatabase;

    private function buys(User $user, string $name, float $lastPaid, int $times = 1, string $vendor = 'Rewe'): Product
    {
        $product = Product::factory()->for($user)->create(['name' => $name, 'normalized_name' => (string) str($name)->slug(), 'category_id' => null]);

        PriceObservation::factory()->for($product)->count($times)->create([
            'unit_price' => $lastPaid,
            'vendor' => $vendor,
            'observed_at' => now()->subDays(3),
        ]);

        return $product;
    }

    private function offer(string $title, float $price, Retailer $retailer = Retailer::Lidl, string $postalCode = '10115'): Deal
    {
        return Deal::factory()->availableIn($postalCode)->create([
            'title' => $title,
            'brand' => null,
            'price' => $price,
            'retailer' => $retailer,
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
        $this->buys($user, 'Freil. Eier', 2.49, times: 3);
        $eggs = $this->offer('Bio Freiland Eier', 1.79);
        $this->offer('Kaffee', 4.99);

        $response = $this->actingAs($user)->getJson(route('deals.for-you'))->assertOk();

        $response->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.offer.id', $eggs->id)
            ->assertJsonPath('recommendations.0.product.name', 'Freil. Eier')
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

    public function test_recommendations_are_ranked_by_saving_then_by_how_often_the_product_is_bought(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();
        $this->buys($user, 'Milch', 1.19, times: 9);
        $this->buys($user, 'Butter', 2.49, times: 1);
        $this->buys($user, 'Joghurt', 0.89, times: 4);
        $milk = $this->offer('Vollmilch', 0.99);
        $butter = $this->offer('Markenbutter', 1.79);
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
            $this->buys($user, $name, 3.00);
            $this->offer($name.' Angebot', 1.00);
        }

        $this->actingAs($user)->getJson(route('deals.for-you', ['limit' => 2]))
            ->assertJsonCount(2, 'recommendations')
            ->assertJsonPath('summary.match_count', 3)
            ->assertJsonPath('summary.possible_saving', 6);
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
