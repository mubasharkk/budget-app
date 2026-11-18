<?php

namespace Tests\Feature;

use App\Domain\Products\Services\ProductMatchingService;
use App\Domain\Shared\Services\LlmService;
use App\Enums\LineItemType;
use App\Jobs\MatchReceiptItems;
use App\Models\ConsumableGroup;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchReceiptItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_line_items_are_matched_to_existing_products_and_price_observations_are_recorded(): void
    {
        $user = User::factory()->create();
        $existing = Product::factory()->for($user)->create([
            'name' => 'Milk 1L',
            'normalized_name' => 'milk-1l',
        ]);

        $receipt = Receipt::factory()->for($user)->create([
            'status' => 'processed',
            'vendor' => 'REWE',
            'currency' => 'EUR',
            'receipt_date' => '2026-06-01',
        ]);

        $item = ReceiptItem::factory()->for($receipt)->create([
            'name' => 'MILCH 1L',
            'unit_price' => 1.29,
            'quantity' => 2,
        ]);

        $this->mock(LlmService::class, function ($mock) use ($item, $existing): void {
            $mock->shouldReceive('matchLineItemsToProducts')
                ->once()
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'matches' => [
                            [
                                'receipt_item_id' => $item->id,
                                'action' => 'existing',
                                'product_id' => $existing->id,
                            ],
                        ],
                    ],
                ]);
        });

        (new MatchReceiptItems($receipt))->handle(app(ProductMatchingService::class));

        $item->refresh();
        $this->assertSame($existing->id, $item->product_id);

        $observation = PriceObservation::where('receipt_item_id', $item->id)->first();
        $this->assertNotNull($observation);
        $this->assertSame($existing->id, $observation->product_id);
        $this->assertSame('REWE', $observation->vendor);
        $this->assertSame('1.2900', (string) $observation->unit_price);
    }

    public function test_new_products_are_created_when_llm_returns_new_action(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create([
            'status' => 'processed',
            'vendor' => 'ALDI',
            'receipt_date' => '2026-06-02',
        ]);

        $item = ReceiptItem::factory()->for($receipt)->create([
            'name' => 'BIO BROT 500G',
            'unit_price' => 2.49,
        ]);

        $this->mock(LlmService::class, function ($mock) use ($item): void {
            $mock->shouldReceive('matchLineItemsToProducts')
                ->once()
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'matches' => [
                            [
                                'receipt_item_id' => $item->id,
                                'action' => 'new',
                                'product' => [
                                    'name' => 'Organic Bread 500g',
                                    'normalized_name' => 'organic-bread-500g',
                                    'brand' => null,
                                    'unit' => 'g',
                                    'size' => '500',
                                ],
                            ],
                        ],
                    ],
                ]);
        });

        (new MatchReceiptItems($receipt))->handle(app(ProductMatchingService::class));

        $item->refresh();
        $this->assertNotNull($item->product_id);

        $product = Product::find($item->product_id);
        $this->assertSame('Organic Bread 500g', $product->name);
        $this->assertSame('organic-bread-500g', $product->normalized_name);
        $this->assertSame($user->id, $product->user_id);
    }

    public function test_products_get_a_reused_general_name_and_deposit_lines_get_no_product(): void
    {
        $user = User::factory()->create();
        $milk = ConsumableGroup::factory()->for($user)->create(['name' => 'Milk', 'unit' => 'l']);
        $receipt = Receipt::factory()->for($user)->create(['status' => 'processed']);
        $item = ReceiptItem::factory()->for($receipt)->create(['name' => 'H-MILCH 1,5L']);
        $deposit = ReceiptItem::factory()->for($receipt)->create(['name' => 'PFAND 0,25', 'unit_price' => 0.25, 'quantity' => 1]);

        $this->mock(LlmService::class, function ($mock) use ($item, $deposit): void {
            $mock->shouldReceive('matchLineItemsToProducts')
                ->once()
                ->withArgs(fn (array $lines, array $products, array $groups): bool => $groups === [['name' => 'Milk', 'unit' => 'l']])
                ->andReturn(['success' => true, 'data' => ['matches' => [
                    [
                        'receipt_item_id' => $item->id,
                        'line_type' => 'product',
                        'action' => 'new',
                        'product' => ['name' => 'UHT Milk 1.5L', 'normalized_name' => 'uht-milk-1-5l', 'unit' => 'l', 'size' => '1.5'],
                        'group' => 'milk',
                        'group_unit' => 'l',
                    ],
                    ['receipt_item_id' => $deposit->id, 'line_type' => 'deposit', 'action' => 'skip'],
                ]]]);
        });

        (new MatchReceiptItems($receipt))->handle(app(ProductMatchingService::class));

        $this->assertSame($milk->id, $item->fresh()->product->consumable_group_id);
        $this->assertSame(1, ConsumableGroup::count());
        $this->assertSame(LineItemType::Deposit, $deposit->fresh()->line_type);
        $this->assertNull($deposit->fresh()->product_id);
        $this->assertSame(0, PriceObservation::where('receipt_item_id', $deposit->id)->count());
    }

    public function test_skips_non_processed_receipts(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->pending()->create();
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Milk']);

        $this->mock(LlmService::class, function ($mock): void {
            $mock->shouldNotReceive('matchLineItemsToProducts');
        });

        (new MatchReceiptItems($receipt))->handle(app(ProductMatchingService::class));

        $this->assertDatabaseCount('products', 0);
    }
}
