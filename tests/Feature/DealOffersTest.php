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
     * The Deals page props, as the browser receives them.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function page(array $query = [], ?User $user = null): array
    {
        $response = $this->actingAs($user ?? $this->user)->get(route('deals', $query))->assertOk();

        return json_decode(json_encode($response->viewData('page')['props']), true);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function offerGroups(array $query = []): array
    {
        return $this->page($query)['offers'];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, int>
     */
    private function offerIds(array $query = []): array
    {
        return collect($this->offerGroups($query))->flatMap(fn (array $group) => array_column($group['offers'], 'id'))->all();
    }

    public function test_without_a_postal_code_no_offers_are_loaded(): void
    {
        $page = $this->page(user: User::factory()->create());

        $this->assertNull($page['offers']);
        $this->assertNull($page['syncedAt']);
    }

    public function test_offers_are_grouped_by_category_with_the_largest_group_first(): void
    {
        $dairy = Category::factory()->create(['name' => 'Dairy']);
        $bakery = Category::factory()->create(['name' => 'Bakery']);
        Deal::factory()->availableIn('10115')->inCategory($bakery)->create();
        Deal::factory()->availableIn('10115')->inCategory($dairy)->count(2)->create();
        Deal::factory()->availableIn('10115')->create();

        $groups = $this->offerGroups();

        $this->assertSame(['Dairy', 'Bakery', 'Other offers'], array_column($groups, 'name'));
        $this->assertSame([2, 1, 1], array_column($groups, 'total'));
        $this->assertSame([$dairy->id, $bakery->id, 0], array_column($groups, 'category_id'));
    }

    public function test_each_group_is_truncated_and_one_category_can_be_expanded_in_place(): void
    {
        $dairy = Category::factory()->create();
        Deal::factory()->availableIn('10115')->inCategory($dairy)->count(8)->create();
        Deal::factory()->availableIn('10115')->count(2)->create();

        [$dairyGroup, $otherGroup] = $this->offerGroups(['per_group' => 1]);
        $this->assertSame([8, 1], [$dairyGroup['total'], count($dairyGroup['offers'])]);
        $this->assertCount(1, $otherGroup['offers']);

        [$dairyGroup, $otherGroup] = $this->offerGroups(['per_group' => 1, 'expand' => $dairy->id]);
        $this->assertCount(8, $dairyGroup['offers']);
        $this->assertCount(1, $otherGroup['offers']);

        [, $otherGroup] = $this->offerGroups(['per_group' => 1, 'expand' => 0]);
        $this->assertSame('Other offers', $otherGroup['name']);
        $this->assertCount(2, $otherGroup['offers']);
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
            'external_url' => 'https://filiale.kaufland.de/angebote/uebersicht.html?kloffer-articleID=1',
        ]);

        $this->assertSame([
            'id' => $deal->id,
            'retailer' => 'lidl',
            'retailer_label' => 'Lidl',
            'category_id' => null,
            'title' => 'Gouda jung',
            'brand' => 'Milbona',
            'description' => $deal->description,
            'external_url' => 'https://filiale.kaufland.de/angebote/uebersicht.html?kloffer-articleID=1',
            'price' => 1.99,
            'old_price' => 2.99,
            'discount_percent' => 33,
            'unit_price' => 4.98,
            'unit_price_unit' => 'kg',
            'valid_from' => '2026-10-26',
            'valid_to' => now()->addDays(2)->toDateString(),
        ], $this->offerGroups()[0]['offers'][0]);
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
        $page = $this->page();

        $this->assertNull($page['syncedAt']);
        $this->assertSame([], $page['offers']);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->user)
            ->get(route('deals', ['tab' => 'other', 'retailer' => 'metro', 'sort' => 'random', 'week' => 'last', 'expand' => -1]))
            ->assertSessionHasErrors(['tab', 'retailer', 'sort', 'week', 'expand']);
    }

    public function test_the_deals_page_lists_the_filter_options_and_echoes_the_url_filters(): void
    {
        $this->actingAs($this->user)
            ->get(route('deals', ['q' => 'eier', 'retailer' => 'aldi', 'sort' => 'price', 'mine' => 1]))
            ->assertInertia(fn ($page) => $page
                ->component('Deals/Index')
                ->has('retailers', 7)
                ->has('sortOptions', 4)
                ->has('weekOptions', 2)
                ->where('tab', 'offers')
                ->where('filters', ['q' => 'eier', 'retailer' => 'aldi', 'sort' => 'price', 'week' => 'current', 'mine' => true, 'expand' => null]));
    }

    public function test_stores_without_offers_in_the_area_are_reported_per_week(): void
    {
        Deal::factory()->availableIn('10115')->create(['retailer' => Retailer::Lidl]);
        Deal::factory()->availableIn('10115')->create(['retailer' => Retailer::Rewe]);
        Deal::factory()->availableIn('10115')->upcoming()->create(['retailer' => Retailer::Penny]);
        Deal::factory()->availableIn('80331')->create(['retailer' => Retailer::Aldi]);

        $this->assertSame(['lidl', 'rewe'], $this->page()['availableRetailers']);
        $this->assertSame(['penny'], $this->page(['week' => 'upcoming'])['availableRetailers']);
        $this->assertNull($this->page(user: User::factory()->create())['availableRetailers']);
    }

    public function test_only_the_open_tabs_data_is_sent(): void
    {
        $this->actingAs($this->user)
            ->get(route('deals'))
            ->assertInertia(fn ($page) => $page
                ->has('offers')
                ->missing('forYou')
                ->missing('comparisons')
                ->missing('prices')
                ->loadDeferredProps('personal', fn ($reload) => $reload->has('forYou.summary')->has('comparisons'))
                ->reloadOnly('prices', fn ($reload) => $reload->has('prices.savings_opportunities')));

        $this->actingAs($this->user)
            ->get(route('deals', ['tab' => 'prices', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30']))
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'prices')
                ->where('priceRange', ['start_date' => '2026-01-01', 'end_date' => '2026-06-30'])
                ->missing('offers')
                ->loadDeferredProps('personal', fn ($reload) => $reload->has('prices.price_trends')));
    }

    public function test_guests_cannot_see_deals(): void
    {
        $this->get(route('deals'))->assertRedirect(route('login'));
    }
}
