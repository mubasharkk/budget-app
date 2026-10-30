<?php

namespace Tests\Feature;

use App\Enums\Retailer;
use App\Models\Category;
use App\Models\Deal;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DealOffersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->user = User::factory()->withPostalCode('10115')->create();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function browse(array $query = []): TestResponse
    {
        return $this->actingAs($this->user)->getJson(route('deals.offers', $query))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, int>
     */
    private function offerIds(array $query = []): array
    {
        return collect($this->browse($query)->json('groups'))->flatMap(fn (array $group) => array_column($group['offers'], 'id'))->all();
    }

    public function test_without_a_postal_code_the_user_is_asked_for_one(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('deals.offers'))
            ->assertStatus(409)
            ->assertJson(['postal_code_required' => true]);
    }

    public function test_offers_are_grouped_by_category_with_the_largest_group_first(): void
    {
        $dairy = Category::factory()->create(['name' => 'Dairy']);
        $bakery = Category::factory()->create(['name' => 'Bakery']);
        Deal::factory()->availableIn('10115')->inCategory($bakery)->create();
        Deal::factory()->availableIn('10115')->inCategory($dairy)->count(2)->create();
        Deal::factory()->availableIn('10115')->create();

        $groups = $this->browse()->json('groups');

        $this->assertSame(['Dairy', 'Bakery', 'Other offers'], array_column($groups, 'name'));
        $this->assertSame([2, 1, 1], array_column($groups, 'total'));
        $this->assertSame([$dairy->id, $bakery->id, 0], array_column($groups, 'category_id'));
    }

    public function test_each_group_is_truncated_and_a_category_can_be_expanded(): void
    {
        $dairy = Category::factory()->create();
        Deal::factory()->availableIn('10115')->inCategory($dairy)->count(8)->create();
        Deal::factory()->availableIn('10115')->count(2)->create();

        $this->browse(['per_group' => 3])
            ->assertJsonPath('groups.0.total', 8)
            ->assertJsonCount(3, 'groups.0.offers');

        $this->browse(['category' => $dairy->id])
            ->assertJsonCount(1, 'groups')
            ->assertJsonCount(8, 'groups.0.offers');

        $this->browse(['category' => 0])
            ->assertJsonPath('groups.0.name', 'Other offers')
            ->assertJsonCount(2, 'groups.0.offers');
    }

    public function test_an_offer_is_described_with_its_unit_price(): void
    {
        $deal = Deal::factory()->availableIn('10115')->discounted(1.99, 2.99)->create([
            'retailer' => Retailer::Lidl,
            'title' => 'Gouda jung',
            'brand' => 'Milbona',
            'unit_price' => 4.98,
            'unit_price_unit' => 'kg',
            'valid_from' => '2026-10-26',
            'valid_to' => now()->addDays(2)->toDateString(),
        ]);

        $this->browse()->assertJsonPath('groups.0.offers.0', [
            'id' => $deal->id,
            'retailer' => 'lidl',
            'retailer_label' => 'Lidl',
            'category_id' => null,
            'title' => 'Gouda jung',
            'brand' => 'Milbona',
            'description' => $deal->description,
            'price' => 1.99,
            'old_price' => 2.99,
            'discount_percent' => 33,
            'unit_price' => 4.98,
            'unit_price_unit' => 'kg',
            'valid_from' => '2026-10-26',
            'valid_to' => now()->addDays(2)->toDateString(),
        ])->assertJsonPath('postal_code', '10115');
    }

    public function test_offers_can_be_sorted(): void
    {
        $cheap = Deal::factory()->availableIn('10115')->create(['price' => 0.99, 'unit_price' => 9.90, 'valid_to' => now()->addDays(6)->toDateString()]);
        $pricey = Deal::factory()->availableIn('10115')->create(['price' => 9.99, 'unit_price' => 1.99, 'valid_to' => now()->addDay()->toDateString()]);
        $discounted = Deal::factory()->availableIn('10115')->discounted(5.00, 10.00)->create(['unit_price' => null, 'valid_to' => now()->addDays(3)->toDateString()]);

        $this->assertSame([$discounted->id, $cheap->id, $pricey->id], $this->offerIds(['sort' => 'saving']));
        $this->assertSame([$cheap->id, $discounted->id, $pricey->id], $this->offerIds(['sort' => 'price']));
        $this->assertSame([$pricey->id, $cheap->id, $discounted->id], $this->offerIds(['sort' => 'unit_price']));
        $this->assertSame([$pricey->id, $discounted->id, $cheap->id], $this->offerIds(['sort' => 'ending']));
    }

    public function test_this_week_and_next_week_are_separate(): void
    {
        $current = Deal::factory()->availableIn('10115')->create();
        $upcoming = Deal::factory()->availableIn('10115')->upcoming()->create();
        Deal::factory()->availableIn('10115')->expired()->create();

        $this->assertSame([$current->id], $this->offerIds());
        $this->assertSame([$upcoming->id], $this->offerIds(['week' => 'upcoming']));
    }

    public function test_only_offers_for_the_users_postal_code_are_listed(): void
    {
        $here = Deal::factory()->availableIn('10115')->create();
        $shared = Deal::factory()->availableIn('80331')->create();
        $shared->postalCodes()->create(['postal_code' => '10115', 'fetched_at' => now()]);
        Deal::factory()->availableIn('80331')->create();

        $this->assertEqualsCanonicalizing([$here->id, $shared->id], $this->offerIds());
    }

    public function test_offers_can_be_searched_and_wildcards_are_literal(): void
    {
        $byTitle = Deal::factory()->availableIn('10115')->create(['title' => 'Freiland Eier', 'brand' => null, 'description' => null]);
        $byBrand = Deal::factory()->availableIn('10115')->create(['title' => 'Kaffee', 'brand' => 'EIERHOF', 'description' => null]);
        Deal::factory()->availableIn('10115')->create(['title' => 'Milch', 'brand' => null, 'description' => null]);

        $this->assertEqualsCanonicalizing([$byTitle->id, $byBrand->id], $this->offerIds(['q' => 'eier']));
        $this->assertSame([], $this->offerIds(['q' => '%']));
    }

    public function test_offers_can_be_filtered_to_one_retailer(): void
    {
        $kaufland = Deal::factory()->availableIn('10115')->create(['retailer' => Retailer::Kaufland]);
        Deal::factory()->availableIn('10115')->create(['retailer' => Retailer::Aldi]);

        $this->assertSame([$kaufland->id], $this->offerIds(['retailer' => 'kaufland']));
    }

    public function test_offers_can_be_limited_to_categories_the_user_buys_from(): void
    {
        $groceries = Category::factory()->create();
        $dairy = Category::factory()->childOf($groceries)->create();
        $bakery = Category::factory()->childOf($groceries)->create();
        ReceiptItem::factory()->for(Receipt::factory()->for($this->user))->create(['category_id' => $groceries->id, 'subcategory_id' => $dairy->id]);
        $milk = Deal::factory()->availableIn('10115')->inCategory($dairy)->create();
        Deal::factory()->availableIn('10115')->inCategory($bakery)->create();

        $this->assertSame([$milk->id], $this->offerIds(['mine' => 1]));
        $this->assertCount(2, $this->offerIds(['mine' => 0]));
    }

    public function test_a_postal_code_that_has_not_synced_yet_reports_no_sync_time(): void
    {
        $this->browse()->assertJsonPath('synced_at', null)->assertJsonCount(0, 'groups');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('deals.offers', ['retailer' => 'penny', 'sort' => 'random', 'week' => 'last', 'category' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['retailer', 'sort', 'week', 'category']);
    }

    public function test_the_deals_page_lists_the_filter_options(): void
    {
        $this->actingAs($this->user)
            ->get(route('deals'))
            ->assertInertia(fn ($page) => $page
                ->component('Deals')
                ->has('retailers', 5)
                ->has('sortOptions', 4)
                ->has('weekOptions', 2));
    }

    public function test_guests_cannot_fetch_offers(): void
    {
        $this->getJson(route('deals.offers'))->assertUnauthorized();
    }
}
