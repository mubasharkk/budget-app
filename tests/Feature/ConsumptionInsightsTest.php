<?php

namespace Tests\Feature;

use App\Domain\Analytics\Data\ConsumptionScope;
use App\Domain\Analytics\Services\ConsumptionService;
use App\Domain\Identity\Services\UserSettingsService;
use App\Domain\Shared\Services\LlmService;
use App\Enums\ExpenseType;
use App\Enums\LineItemType;
use App\Jobs\MatchReceiptItems;
use App\Models\Category;
use App\Models\ConsumableGroup;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConsumptionInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_are_totalled_by_general_name_in_its_unit(): void
    {
        $user = User::factory()->create();
        $milk = ConsumableGroup::factory()->for($user)->create(['name' => 'Milk', 'unit' => 'l']);
        $bananas = ConsumableGroup::factory()->for($user)->create(['name' => 'Bananas', 'unit' => 'kg']);
        $litre = Product::factory()->for($user)->create(['name' => 'Milk 1L', 'unit' => 'l', 'size' => '1', 'consumable_group_id' => $milk->id]);
        $uht = Product::factory()->for($user)->create(['name' => 'UHT Milk 1.5L', 'unit' => 'l', 'size' => '1,5', 'consumable_group_id' => $milk->id]);
        $unsized = Product::factory()->for($user)->create(['name' => 'Farm Milk', 'unit' => 'l', 'size' => null, 'consumable_group_id' => $milk->id]);
        $loose = Product::factory()->for($user)->create(['name' => 'Bananas (loose)', 'unit' => 'kg', 'size' => null, 'consumable_group_id' => $bananas->id]);

        $receipt = Receipt::factory()->for($user)->create();
        ReceiptItem::factory()->for($receipt)->create(['name' => 'MILCH 1L', 'quantity' => 2, 'unit_price' => 1, 'product_id' => $litre->id]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'H-MILCH 1,5L', 'quantity' => 1, 'unit_price' => 1.5, 'product_id' => $uht->id]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'HOFMILCH', 'quantity' => 1, 'unit_price' => 2, 'product_id' => $unsized->id]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'BANANEN', 'quantity' => 1.234, 'unit_price' => 2, 'product_id' => $loose->id]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'BREZEL', 'quantity' => 3, 'unit_price' => 0.5]);
        ReceiptItem::factory()->for(Receipt::factory()->for($user))->create(['name' => 'Brezel', 'quantity' => 1, 'unit_price' => 0.5]);

        $items = (new ConsumptionService)->topItems($user->id);

        $this->assertSame(['Milk', 'BREZEL', 'Bananas'], $items->pluck('item_name')->all());

        $milkRow = $items[0];
        $this->assertSame(3, $milkRow->purchase_count);
        $this->assertSame(3.5, $milkRow->amount);
        $this->assertSame('l', $milkRow->unit);
        $this->assertSame(1, $milkRow->unmeasured_count);
        $this->assertEqualsCanonicalizing(['Milk 1L', 'UHT Milk 1.5L', 'Farm Milk'], $milkRow->variants);

        $this->assertSame(2, $items[1]->purchase_count);
        $this->assertNull($items[1]->amount);
        $this->assertSame([1.234, 'kg'], [$items[2]->amount, $items[2]->unit]);
    }

    public function test_only_personal_product_lines_outside_excluded_categories_count(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $dairy = Category::factory()->childOf($groceries)->create(['name' => 'Dairy']);
        $household = Category::factory()->create(['name' => 'Household']);

        $personal = Receipt::factory()->for($user)->create();
        ReceiptItem::factory()->for($personal)->create(['name' => 'Cheese', 'category_id' => $groceries->id, 'subcategory_id' => $dairy->id]);
        ReceiptItem::factory()->for($personal)->create(['name' => 'Detergent', 'category_id' => $household->id]);
        ReceiptItem::factory()->for($personal)->create(['name' => 'PFAND', 'line_type' => LineItemType::Deposit]);
        ReceiptItem::factory()->for($personal)->create(['name' => 'Coupon', 'line_type' => LineItemType::Discount, 'unit_price' => -1, 'quantity' => 1]);
        $business = Receipt::factory()->for($user)->create(['expense_type' => ExpenseType::Business]);
        ReceiptItem::factory()->for($business)->create(['name' => 'Office coffee']);

        $names = fn (?ConsumptionScope $scope = null): array => (new ConsumptionService)
            ->topItems($user->id, 'spend', scope: $scope)->pluck('item_name')->sort()->values()->all();

        $this->assertSame(['Cheese', 'Detergent'], $names());
        $this->assertSame(['Cheese', 'Detergent', 'Office coffee'], $names(new ConsumptionScope(includeBusiness: true)));
        $this->assertSame(['Detergent'], $names(new ConsumptionScope(excludedCategoryIds: [$groceries->id])));
    }

    public function test_excluded_categories_are_saved_per_user_and_applied_to_the_report(): void
    {
        $user = User::factory()->create(['settings' => ['dashboard' => ['sections' => ['tasks']]]]);
        $household = Category::factory()->create(['name' => 'Household']);
        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => now()]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Detergent', 'category_id' => $household->id]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Bread']);

        $this->actingAs($user)
            ->putJson(route('insights.settings.update'), ['excluded_category_ids' => [$household->id]])
            ->assertOk()
            ->assertJsonPath('excluded_category_ids', [$household->id]);

        $this->assertSame(['tasks'], $user->fresh()->settings['dashboard']['sections']);

        $this->actingAs($user)->getJson(route('dashboard.consumption'))
            ->assertOk()
            ->assertJsonPath('excluded_category_ids', [$household->id])
            ->assertJsonPath('top_by_spend.*.item_name', ['Bread']);

        $this->actingAs($user)
            ->putJson(route('insights.settings.update'), ['excluded_category_ids' => [999]])
            ->assertJsonValidationErrors('excluded_category_ids.0');
    }

    public function test_the_dashboard_widget_lists_general_names_with_their_products_and_totals(): void
    {
        $user = User::factory()->create();
        $household = Category::factory()->create();
        app(UserSettingsService::class)->updateExcludedConsumptionCategoryIds($user, [$household->id]);
        $milk = ConsumableGroup::factory()->for($user)->create(['name' => 'Milk', 'unit' => 'l']);
        $litre = Product::factory()->for($user)->create(['name' => 'Milk 1L', 'unit' => 'l', 'size' => '1', 'consumable_group_id' => $milk->id]);
        $uht = Product::factory()->for($user)->create(['name' => 'UHT Milk 1.5L', 'unit' => 'l', 'size' => '1.5', 'consumable_group_id' => $milk->id]);

        Carbon::setTestNow('2026-11-18 10:00:00');
        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => '2026-11-10']);
        ReceiptItem::factory()->for($receipt)->create(['quantity' => 2, 'unit_price' => 1, 'product_id' => $litre->id]);
        $sameStretchLastMonth = Receipt::factory()->for($user)->create(['receipt_date' => '2026-10-15']);
        ReceiptItem::factory()->for($sameStretchLastMonth)->create(['quantity' => 2, 'unit_price' => 1, 'product_id' => $litre->id]);
        $laterLastMonth = Receipt::factory()->for($user)->create(['receipt_date' => '2026-10-25']);
        ReceiptItem::factory()->for($laterLastMonth)->create(['quantity' => 10, 'unit_price' => 1, 'product_id' => $litre->id]);
        ReceiptItem::factory()->for($receipt)->create(['quantity' => 1, 'unit_price' => 1.5, 'product_id' => $uht->id]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Brezel', 'quantity' => 1, 'unit_price' => 0.5]);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Detergent', 'category_id' => $household->id]);

        $this->actingAs($user)->getJson(route('dashboard.items-consumed', ['period' => 'month', 'limit' => 10]))
            ->assertOk()
            ->assertJsonPath('totals.spend', 4)
            ->assertJsonPath('totals.purchases', 3)
            ->assertJsonPath('totals.labels', 2)
            ->assertJsonPath('items.0.label', 'Milk')
            ->assertJsonPath('items.0.url', route('insights.groups.show', $milk))
            ->assertJsonPath('items.0.amount_label', '3.5 l')
            ->assertJsonPath('items.0.purchases', 2)
            ->assertJsonPath('items.0.share_percent', 87.5)
            ->assertJsonPath('items.0.change_percent', 75)
            ->assertJsonPath('totals.previous_spend', 2)
            ->assertJsonPath('totals.change_percent', 100)
            ->assertJsonPath('items.1.is_new', true)
            ->assertJsonPath('items.1.label', 'Brezel')
            ->assertJsonPath('items.1.url', null)
            ->assertJsonPath('items.1.amount_label', null)
            ->assertJsonPath('items.1.products', []);

        $products = $this->actingAs($user)->getJson(route('dashboard.items-consumed', ['period' => 'month']))->json('items.0.products');
        $this->assertEqualsCanonicalizing(['Milk 1L', 'UHT Milk 1.5L'], $products);
        Carbon::setTestNow();
    }

    public function test_backfill_labels_lines_queues_matching_and_names_products(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        ConsumableGroup::factory()->for($user)->create(['name' => 'Coffee beans', 'unit' => 'kg']);
        $product = Product::factory()->for($user)->create(['name' => 'Espresso Beans 1kg']);
        $receipt = Receipt::factory()->for($user)->create(['status' => 'processed']);
        $deposit = ReceiptItem::factory()->for($receipt)->create(['name' => 'Pfand 0,25']);
        $bag = ReceiptItem::factory()->for($receipt)->create(['name' => 'Papiertüte']);
        $teaBag = ReceiptItem::factory()->for($receipt)->create(['name' => 'Tea bags']);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Espresso', 'product_id' => $product->id]);

        $this->mock(LlmService::class, function ($mock) use ($product): void {
            $mock->shouldReceive('groupProducts')->once()->andReturn(['success' => true, 'data' => ['groups' => [
                ['product_id' => $product->id, 'group' => 'coffee beans', 'group_unit' => 'kg'],
            ]]]);
        });

        $this->artisan('consumption:backfill')->assertSuccessful();

        $this->assertSame(LineItemType::Deposit, $deposit->fresh()->line_type);
        $this->assertSame(LineItemType::Bag, $bag->fresh()->line_type);
        $this->assertSame(LineItemType::Product, $teaBag->fresh()->line_type);
        Queue::assertPushed(MatchReceiptItems::class, fn (MatchReceiptItems $job): bool => $job->receipt->is($receipt));
        $this->assertSame('Coffee beans', $product->fresh()->consumableGroup->name);
        $this->assertSame(1, ConsumableGroup::count());
    }
}
