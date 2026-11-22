<?php

namespace Tests\Feature;

use App\Enums\Retailer;
use App\Models\ConsumableGroup;
use App\Models\Deal;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsNextStepsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-22 12:00:00');
        $this->user = User::factory()->withPostalCode('10115')->create(['default_currency' => 'EUR']);
        $this->actingAs($this->user);
    }

    /**
     * @param  array<int, string>  $dates
     */
    private function buy(?Product $product, string $name, array $dates, float $price, float $quantity = 1, string $vendor = 'REWE', string $currency = 'EUR'): void
    {
        foreach ($dates as $date) {
            ReceiptItem::factory()
                ->for(Receipt::factory()->for($this->user)->create(['receipt_date' => $date, 'vendor' => $vendor, 'currency' => $currency]))
                ->create(['name' => $name, 'product_id' => $product?->id, 'quantity' => $quantity, 'unit_price' => $price]);
        }
    }

    private function product(string $name, ?string $unit, ?string $size, ?ConsumableGroup $group = null): Product
    {
        return Product::factory()->for($this->user)->create([
            'name' => $name, 'unit' => $unit, 'size' => $size, 'consumable_group_id' => $group?->id,
        ]);
    }

    private function offer(string $title, float $price, float $unitPrice, string $unit, string $postalCode = '10115'): Deal
    {
        return Deal::factory()->availableIn($postalCode)->create([
            'title' => $title, 'brand' => null, 'retailer' => Retailer::Lidl,
            'price' => $price, 'unit_price' => $unitPrice, 'unit_price_unit' => $unit,
        ]);
    }

    public function test_offers_are_compared_per_unit_so_a_smaller_pack_is_not_a_saving(): void
    {
        $this->buy($this->product('Coffee 500g', 'kg', '0.5'), 'KAFFEE', ['2026-11-02', '2026-11-12'], 5, 2);
        $this->offer('Kaffee Packung', 3, 12, 'kg');

        $this->getJson(route('insights.next-steps'))->assertJsonCount(0, 'savings.rows');

        $cheaper = $this->offer('Kaffee gemahlen', 4, 8, 'kg');
        $this->offer('Kaffee', 3, 7, 'kg', postalCode: '80331');

        $this->getJson(route('insights.next-steps'))
            ->assertJsonPath('savings.rows.0.offer.id', $cheaper->id)
            ->assertJsonPath('savings.rows.0.saving', 2)
            ->assertJsonPath('savings.total', 2);
    }

    public function test_offers_need_whole_words_a_plausible_price_and_a_known_size(): void
    {
        $dates = ['2026-11-02', '2026-11-12'];
        $this->buy($this->product('Ice cream', 'kg', '1'), 'EIS', $dates, 10);
        $this->buy($this->product('Cheese', 'kg', '1'), 'KAESE', $dates, 10);
        $this->buy($this->product('Eggs', 'pcs', null), 'EIER', $dates, 2.5);

        $this->offer('Reis', 7, 7, 'kg');
        $this->offer('Kaese', 2, 2, 'kg');
        $this->offer('Eier', 2, 2, 'Stück');

        $this->getJson(route('insights.next-steps'))->assertJsonCount(0, 'savings.rows');
    }

    public function test_an_offer_for_another_kind_of_the_thing_is_not_a_saving(): void
    {
        $dates = ['2026-11-02', '2026-11-12'];
        $this->buy($this->product('Tomatoes', 'kg', '1'), 'TOMATEN', $dates, 3);
        $this->buy($this->product('Organic bananas', 'kg', '1'), 'BIO BANANEN', $dates, 2);

        $this->offer('Tomaten Ketchup', 2, 2, 'kg');
        $this->offer('Bananen', 1.5, 1.5, 'kg');

        $this->getJson(route('insights.next-steps'))->assertJsonCount(0, 'savings.rows');

        $this->offer('Deutsche Tomaten, versch. Sorten', 2.5, 2.5, 'kg');
        $this->offer('Bio Bananen', 1.8, 1.8, 'kg');

        $this->getJson(route('insights.next-steps'))->assertJsonCount(2, 'savings.rows');
    }

    public function test_another_shop_needs_repeated_lower_prices_for_the_same_product(): void
    {
        $butter = $this->product('Butter', null, null);
        $this->buy($butter, 'BUTTER', ['2026-11-01', '2026-11-05', '2026-11-09'], 2.5);
        $this->buy($butter, 'BUTTER', ['2026-11-03'], 2, vendor: 'LIDL');

        $this->getJson(route('insights.next-steps'))->assertJsonCount(0, 'savings.rows');

        $this->buy($butter, 'BUTTER', ['2026-11-07'], 2, vendor: 'Lidl Vertriebs GmbH');

        $this->getJson(route('insights.next-steps'))
            ->assertJsonPath('savings.rows.0.kind', 'shop')
            ->assertJsonPath('savings.rows.0.saving', 0.5);
    }

    public function test_weighed_goods_whose_weight_was_lost_are_not_compared(): void
    {
        $this->buy($this->product('Bananas', 'kg', null), 'BANANEN', ['2026-11-02', '2026-11-12'], 2);
        $this->offer('Bananen', 1.5, 1.5, 'kg');

        $this->getJson(route('insights.next-steps'))->assertJsonCount(0, 'savings.rows');
    }

    public function test_only_a_regular_rhythm_that_is_still_running_is_due_soon(): void
    {
        $milk = $this->product('Milk', 'l', '1', ConsumableGroup::factory()->for($this->user)->create(['name' => 'Milk', 'unit' => 'l']));
        $this->buy($milk, 'MILCH', ['2026-10-27', '2026-11-03', '2026-11-10', '2026-11-17'], 1.2, 3);
        $this->buy(null, 'Irregular', ['2026-09-01', '2026-09-03', '2026-10-01', '2026-11-20'], 4);
        $this->buy(null, 'Stopped', ['2026-08-01', '2026-08-08', '2026-08-15', '2026-08-22'], 4);
        $this->buy(null, 'Too few', ['2026-11-06', '2026-11-13', '2026-11-20'], 4);

        $this->getJson(route('insights.next-steps'))
            ->assertJsonCount(1, 'due_soon.rows')
            ->assertJsonPath('due_soon.rows.0.label', 'Milk')
            ->assertJsonPath('due_soon.rows.0.days_until', 2)
            ->assertJsonPath('due_soon.rows.0.amount_label', '3 l')
            ->assertJsonPath('due_soon.total', 3.6);
    }

    public function test_a_missing_pack_size_can_be_fixed_and_is_then_measured(): void
    {
        $product = $this->product('Pesto', null, null, ConsumableGroup::factory()->for($this->user)->create(['unit' => null]));
        $this->buy($product, 'PESTO', ['2026-11-02'], 3);

        $this->getJson(route('insights.next-steps'))->assertJsonPath('data_quality.rows.0.missing', ['size']);

        $this->patchJson(route('insights.products.update', $product), ['size' => 190, 'unit' => 'g'])->assertOk();

        $this->assertSame(['kg', '0.19'], [$product->fresh()->unit, $product->fresh()->size]);
        $this->assertSame('kg', $product->fresh()->consumableGroup->unit);
        $this->getJson(route('insights.next-steps'))->assertJsonPath('data_quality.count', 0);
    }

    public function test_a_pack_in_another_unit_than_the_rest_of_its_name_is_refused_unless_it_moves(): void
    {
        $milk = ConsumableGroup::factory()->for($this->user)->create(['name' => 'Milk', 'unit' => 'l']);
        $this->product('Milk 1L', 'l', '1', $milk);
        $powder = $this->product('Milk powder', null, null, $milk);

        $this->patchJson(route('insights.products.update', $powder), ['size' => 400, 'unit' => 'g'])->assertUnprocessable();
        $this->assertNull($powder->fresh()->size);

        $this->patchJson(route('insights.products.update', $powder), ['size' => 400, 'unit' => 'g', 'group_name' => 'Milk powder'])->assertOk();
        $this->assertSame('kg', $powder->fresh()->consumableGroup->unit);
        $this->assertSame('l', $milk->fresh()->unit);
    }

    public function test_unmatched_lines_get_a_product_without_changing_totals_or_other_users_lines(): void
    {
        $this->buy(null, 'Hafer Drink', ['2026-11-02'], 1.5, 2);
        $this->buy(null, 'HAFER DRINK', ['2026-11-09'], 1.5);
        $othersLine = ReceiptItem::factory()->create(['name' => 'Hafer Drink']);

        $this->postJson(route('insights.unmatched-items.store'), ['name' => 'Hafer Drink', 'group_name' => 'Oat drink', 'size' => 1, 'unit' => 'l'])
            ->assertCreated();

        $product = Product::query()->where('user_id', $this->user->id)->sole();
        $this->assertSame('Oat drink', $product->consumableGroup->name);
        $this->assertSame(2, $product->receiptItems()->count());
        $this->assertEquals(4.5, $product->receiptItems()->sum('total'));
        $this->assertNull($othersLine->fresh()->product_id);
    }

    public function test_corrections_are_validated_and_only_for_the_users_own_products(): void
    {
        $product = $this->product('Juice', null, null);

        $this->patchJson(route('insights.products.update', $product), ['unit' => 'ml'])->assertJsonValidationErrors('size');
        $this->patchJson(route('insights.products.update', $product), [])->assertJsonValidationErrors('group_name');
        $this->patchJson(route('insights.products.update', Product::factory()->create()), ['group_name' => 'Mine'])->assertForbidden();
    }

    public function test_offers_are_not_compared_in_another_currency_or_without_a_postal_code(): void
    {
        $this->user->update(['default_currency' => 'PKR']);
        $this->getJson(route('insights.next-steps'))->assertJsonPath('savings.offers_status', 'other_currency');

        $this->user->update(['default_currency' => 'EUR', 'postal_code' => null]);
        $this->getJson(route('insights.next-steps'))->assertJsonPath('savings.offers_status', 'no_postal_code');
    }
}
