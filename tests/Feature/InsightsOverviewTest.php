<?php

namespace Tests\Feature;

use App\Domain\Shared\Services\LlmService;
use App\Enums\ExpenseType;
use App\Enums\LineItemType;
use App\Jobs\ProcessReceipt;
use App\Models\Category;
use App\Models\ConsumableGroup;
use App\Models\Contract;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InsightsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-15 12:00:00');
        $this->user = User::factory()->create(['default_currency' => 'EUR']);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $receipt
     */
    private function buy(string $date, float $unitPrice, array $item = [], array $receipt = []): void
    {
        ReceiptItem::factory()
            ->for(Receipt::factory()->for($this->user)->create([...$receipt, 'receipt_date' => $date]))
            ->create(['quantity' => 1, ...$item, 'unit_price' => $unitPrice]);
    }

    public function test_the_change_is_split_into_parts_that_add_up_and_the_chart_matches_the_headline(): void
    {
        $milk = ConsumableGroup::factory()->for($this->user)->create(['name' => 'Milk', 'unit' => 'l']);
        $fruit = ConsumableGroup::factory()->for($this->user)->create(['name' => 'Fruit', 'unit' => 'kg']);
        $litre = Product::factory()->for($this->user)->create(['unit' => 'l', 'size' => '1', 'consumable_group_id' => $milk->id]);
        $apples = Product::factory()->for($this->user)->create(['name' => 'Apples', 'unit' => null, 'size' => null, 'consumable_group_id' => $fruit->id]);
        $pears = Product::factory()->for($this->user)->create(['name' => 'Pears', 'unit' => null, 'size' => null, 'consumable_group_id' => $fruit->id]);

        $this->buy('2026-10-10', 1, ['name' => 'MILCH', 'quantity' => 2, 'product_id' => $litre->id]);
        $this->buy('2026-10-20', 1, ['name' => 'MILCH', 'quantity' => 2, 'product_id' => $litre->id]);
        $this->buy('2026-11-05', 1.5, ['name' => 'MILCH', 'quantity' => 3, 'product_id' => $litre->id]);
        $this->buy('2026-10-05', 3, ['name' => 'Juice']);
        $this->buy('2026-11-02', 5, ['name' => 'Bread']);
        $this->buy('2026-10-03', 4, ['name' => 'OBST', 'product_id' => $apples->id]);
        $this->buy('2026-11-03', 6, ['name' => 'OBST', 'product_id' => $pears->id]);
        $this->buy('2026-11-04', 0.25, ['name' => 'PFAND', 'line_type' => LineItemType::Deposit]);
        $this->buy('2026-11-04', 1000, ['name' => 'Rice'], ['currency' => 'PKR']);

        $overview = $this->actingAs($this->user)->getJson(route('insights.overview'))
            ->assertOk()
            ->assertJsonPath('totals.spend', 15.5)
            ->assertJsonPath('totals.change', 6.5)
            ->assertJsonPath('totals.forecast.total', 17.5)
            ->assertJsonPath('totals.price_change_percent', 50)
            ->assertJsonPath('changes.effects', ['amount' => 1, 'price' => 1.5, 'new' => 5, 'stopped' => -3, 'unclear' => 2])
            ->assertJsonPath('other_currencies.0.currency', 'PKR')
            ->assertJsonPath('other_currencies.0.spend', 1000)
            ->json();

        $this->assertEquals(15.5, collect($overview['chart']['series'])->sum(fn (array $row): float => $row['variable'][11]));
    }

    public function test_contracts_in_another_currency_are_reported_rather_than_silently_left_out(): void
    {
        Contract::factory()->for($this->user)->create(['amount' => 15, 'currency' => 'USD', 'billing_cycle' => 'monthly']);
        Contract::factory()->for($this->user)->create(['amount' => 9, 'currency' => 'USD', 'billing_cycle' => 'monthly']);
        Contract::factory()->for($this->user)->cancelled()->create(['amount' => 50, 'currency' => 'USD']);
        Contract::factory()->for($this->user)->create(['amount' => 20, 'currency' => 'EUR']);

        $this->actingAs($this->user)->getJson(route('insights.overview'))
            ->assertOk()
            ->assertJsonPath('other_currency_contracts', [['currency' => 'USD', 'monthly' => 24, 'contracts' => 2]]);
    }

    public function test_the_forecast_works_before_anything_was_bought_in_the_earlier_stretch(): void
    {
        $this->travelTo('2026-11-01 12:00:00');
        $this->buy('2026-09-01', 40);
        $this->buy('2026-10-20', 100);
        $this->buy('2026-11-01', 5);

        $this->actingAs($this->user)->getJson(route('insights.overview'))
            ->assertJsonPath('totals.forecast.total', 105)
            ->assertJsonPath('totals.change', null);
    }

    public function test_a_comparison_with_a_partly_recorded_period_says_so_and_is_not_projected(): void
    {
        $this->buy('2026-10-12', 10);
        $this->buy('2026-11-03', 30);

        $this->actingAs($this->user)->getJson(route('insights.overview'))
            ->assertJsonPath('records_start', '2026-10-12')
            ->assertJsonPath('totals.forecast', null);
    }

    public function test_dates_that_make_a_month_compare_with_the_month_before(): void
    {
        $this->actingAs($this->user);

        $this->getJson(route('insights.overview', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']))
            ->assertJsonPath('window.previous_start', '2026-09-01')
            ->assertJsonPath('window.previous_end', '2026-09-30');

        $this->getJson(route('insights.overview', ['start_date' => '2026-11-01', 'end_date' => '2026-11-15']))
            ->assertJsonPath('window.label', 'This month')
            ->assertJsonPath('window.previous_start', '2026-10-01')
            ->assertJsonPath('window.previous_end', '2026-10-15');

        $this->getJson(route('insights.overview', ['start_date' => '2026-11-01', 'end_date' => '2026-12-31']))
            ->assertUnprocessable();
    }

    public function test_a_subcategory_filters_every_part_of_the_page(): void
    {
        $food = Category::factory()->create();
        $dairy = Category::factory()->create(['parent_id' => $food->id]);
        $this->buy('2026-11-02', 7, ['category_id' => $food->id, 'subcategory_id' => $dairy->id], ['vendor' => 'Corner Shop']);
        $this->buy('2026-11-02', 20, ['category_id' => $food->id]);

        $this->actingAs($this->user)->getJson(route('insights.overview', ['category_id' => $dairy->id]))
            ->assertJsonPath('totals.spend', 7)
            ->assertJsonPath('shops.0.total_spent', 7)
            ->assertJsonPath('chart.series.0.variable.11', 7)
            ->assertJsonCount(1, 'items');
    }

    public function test_a_contract_stops_counting_when_it_stopped_and_never_in_months_to_come(): void
    {
        Contract::factory()->for($this->user)->create(['amount' => 10, 'billing_cycle' => 'monthly', 'start_date' => '2026-01-01', 'end_date' => null]);
        Contract::factory()->for($this->user)->cancelled()->create(['amount' => 100, 'billing_cycle' => 'monthly', 'start_date' => '2026-01-01', 'end_date' => null, 'last_paid_at' => '2026-03-10']);

        $this->actingAs($this->user)->getJson(route('dashboard.consumption', ['year' => 2026]))
            ->assertJsonPath('spend_series.0.fixed.3', 110)
            ->assertJsonPath('spend_series.0.fixed.4', 10)
            ->assertJsonPath('spend_series.0.fixed.12', 0);
    }

    public function test_the_products_page_follows_the_saved_scope_and_saving_categories_keeps_the_business_choice(): void
    {
        $group = ConsumableGroup::factory()->for($this->user)->create();
        $product = Product::factory()->for($this->user)->create(['consumable_group_id' => $group->id]);
        $this->buy('2026-11-02', 10, ['product_id' => $product->id], ['expense_type' => ExpenseType::Business]);

        $this->actingAs($this->user);
        $this->putJson(route('insights.settings.update'), ['excluded_category_ids' => [], 'include_business' => true]);
        $this->putJson(route('insights.settings.update'), ['excluded_category_ids' => []])
            ->assertJsonPath('include_business', true);

        $this->get(route('insights.groups.show', $group))
            ->assertInertia(fn ($page) => $page->where('totals.spend', 10)->has('purchases', 1));
    }

    public function test_an_imported_subcategory_is_filed_under_its_own_parent(): void
    {
        Queue::fake();
        Storage::fake('local');
        $food = Category::factory()->create(['name' => 'Food', 'slug' => 'food']);
        Category::factory()->create(['name' => 'Snacks', 'slug' => 'snacks', 'parent_id' => $food->id]);
        $travel = Category::factory()->create(['name' => 'Travel', 'slug' => 'travel']);

        $receipt = Receipt::factory()->for($this->user)->pending()->create();
        $receipt->addMediaFromString('fake-image-bytes')->usingFileName('receipt.png')->toMediaCollection(Receipt::RECEIPT_COLLECTION);

        $line = fn (string $subcategory): array => ['name' => $subcategory, 'quantity' => 1, 'unit_price' => 2, 'total' => 2, 'category' => 'Travel', 'subcategory' => $subcategory];
        $this->mock(LlmService::class)->shouldReceive('parseReceiptFromFile')->andReturn(['success' => true, 'data' => [
            'is_receipt' => true,
            'vendor' => 'Kiosk',
            'currency' => 'EUR',
            'total_amount' => 4,
            'date' => '2026-11-10',
            'items' => [$line('Snacks'), $line('Snacks!')],
        ]]);

        (new ProcessReceipt($receipt->fresh()))->handle(app(LlmService::class));

        $filed = ReceiptItem::query()->pluck('subcategory_id')->unique();
        $this->assertCount(1, $filed);
        $this->assertSame($travel->id, Category::query()->find($filed->sole())->parent_id);
    }
}
