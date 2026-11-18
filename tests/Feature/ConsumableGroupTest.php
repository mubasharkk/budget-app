<?php

namespace Tests\Feature;

use App\Domain\Products\Services\ProductMatchingService;
use App\Domain\Shared\Services\LlmService;
use App\Enums\LineItemType;
use App\Jobs\MatchReceiptItems;
use App\Models\ConsumableGroup;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConsumableGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-18 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_page_shows_amounts_frequency_and_the_cheapest_shop(): void
    {
        $user = User::factory()->create();
        $milk = ConsumableGroup::factory()->for($user)->create(['name' => 'Milk', 'unit' => 'l']);
        $litre = Product::factory()->for($user)->create(['name' => 'Milk 1L', 'unit' => 'l', 'size' => '1', 'consumable_group_id' => $milk->id]);
        $bigPack = Product::factory()->for($user)->create(['name' => 'Milk 1.5L', 'unit' => 'l', 'size' => '1.5', 'consumable_group_id' => $milk->id]);

        $rewe = Receipt::factory()->for($user)->create(['vendor' => 'REWE', 'receipt_date' => '2026-11-01']);
        ReceiptItem::factory()->for($rewe)->create(['quantity' => 2, 'unit_price' => 1.20, 'product_id' => $litre->id]);
        $aldi = Receipt::factory()->for($user)->create(['vendor' => 'ALDI', 'receipt_date' => '2026-11-11']);
        ReceiptItem::factory()->for($aldi)->create(['quantity' => 2, 'unit_price' => 1.35, 'product_id' => $bigPack->id]);
        $left = ReceiptItem::factory()->for($aldi)->create(['quantity' => 1, 'unit_price' => 1, 'product_id' => $litre->id, 'line_type' => LineItemType::Excluded]);
        ReceiptItem::factory()->for(Receipt::factory())->create(['product_id' => $litre->id]);

        $this->actingAs($user)->get(route('insights.groups.show', $milk))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Insights/Group')
                ->where('totals.amount', 5)
                ->where('totals.spend', 5.1)
                ->where('totals.purchases', 2)
                ->where('totals.days_between_purchases', 10)
                ->where('totals.price_per_unit', 1.02)
                ->where('vendors.0.vendor', 'ALDI')
                ->where('vendors.0.price_per_unit', 0.9)
                ->where('monthly.11.amount', 5)
                ->has('purchases', 3)
                ->where('purchases.0.id', $left->id)
                ->where('purchases.0.counts', false)
            );

        $this->actingAs(User::factory()->create())->get(route('insights.groups.show', $milk))->assertForbidden();
    }

    public function test_renaming_to_an_existing_name_merges_and_products_can_move(): void
    {
        $user = User::factory()->create();
        $milk = ConsumableGroup::factory()->for($user)->create(['name' => 'Milk', 'unit' => 'l']);
        $freshMilk = ConsumableGroup::factory()->for($user)->create(['name' => 'Fresh milk', 'unit' => 'l']);
        $farmMilk = Product::factory()->for($user)->create(['consumable_group_id' => $freshMilk->id]);
        $oatDrink = Product::factory()->for($user)->create(['consumable_group_id' => $milk->id]);

        $this->actingAs($user)->patch(route('insights.groups.update', $freshMilk), ['name' => 'milk', 'unit' => 'l'])
            ->assertRedirect(route('insights.groups.show', $milk));
        $this->assertModelMissing($freshMilk);
        $this->assertSame($milk->id, $farmMilk->fresh()->consumable_group_id);

        $this->actingAs($user)->patch(route('products.consumable-group.update', $oatDrink), ['group_name' => 'oat drink'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Oat drink', $oatDrink->fresh()->consumableGroup->name);
        $this->assertModelExists($milk);

        $this->actingAs($user)->patch(route('products.consumable-group.update', $farmMilk), ['group_name' => 'Oat drink']);
        $this->assertModelMissing($milk);

        $someoneElses = ConsumableGroup::factory()->create();
        $this->actingAs($user)->post(route('insights.groups.merge', $farmMilk->fresh()->consumableGroup), ['into' => $someoneElses->id])
            ->assertSessionHasErrors('into');
        $this->actingAs($user)->patch(route('insights.groups.update', $someoneElses), ['name' => 'Mine'])->assertForbidden();
    }

    public function test_a_line_left_out_stops_counting_and_stays_out_when_matched_again(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create(['status' => 'processed']);
        $item = ReceiptItem::factory()->for($receipt)->create(['name' => 'Party cake']);

        $this->actingAs($user)->patch(route('receipt-items.consumption.update', $item), ['counts' => false]);
        $this->assertSame(LineItemType::Excluded, $item->fresh()->line_type);

        $this->mock(LlmService::class, function ($mock) use ($item): void {
            $mock->shouldReceive('matchLineItemsToProducts')->andReturn(['success' => true, 'data' => ['matches' => [[
                'receipt_item_id' => $item->id, 'line_type' => 'product', 'action' => 'new',
                'product' => ['name' => 'Cake', 'normalized_name' => 'cake'], 'group' => 'Cake', 'group_unit' => 'pcs',
            ]]]]);
        });
        (new MatchReceiptItems($receipt))->handle(app(ProductMatchingService::class));

        $this->assertSame(LineItemType::Excluded, $item->fresh()->line_type);
        $this->assertNotNull($item->fresh()->product_id);

        $this->actingAs($user)->patch(route('receipt-items.consumption.update', $item), ['counts' => true]);
        $this->assertSame(LineItemType::Product, $item->fresh()->line_type);

        $this->actingAs(User::factory()->create())
            ->patch(route('receipt-items.consumption.update', $item), ['counts' => false])
            ->assertForbidden();
    }
}
