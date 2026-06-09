<?php

namespace Tests\Feature;

use App\Jobs\MatchReceiptItems;
use App\Jobs\ProcessReceipt;
use App\Models\Category;
use App\Models\Receipt;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function fakeReceipt(): Receipt
    {
        Storage::fake('public');
        $receipt = Receipt::factory()->pending()->create([
            'stored_path' => 'receipts/2026/06/test.png',
            'mime' => 'image/png',
        ]);
        Storage::disk('public')->put($receipt->stored_path, 'fake-image-bytes');

        return $receipt;
    }

    private function mockLlm(array $data): void
    {
        $this->mock(LlmService::class, function ($mock) use ($data) {
            $mock->shouldReceive('parseReceiptFromFile')
                ->once()
                ->andReturn(['success' => true, 'data' => $data]);
        });
    }

    public function test_receipt_is_parsed_and_items_with_categories_are_created(): void
    {
        $receipt = $this->fakeReceipt();

        $this->mockLlm([
            'is_receipt' => true,
            'vendor' => 'REWE',
            'currency' => 'EUR',
            'total_amount' => 14.39,
            'receipt_date' => '2026-05-01',
            'receipt_time' => '14:58:00',
            'items' => [
                ['name' => 'MILCH', 'quantity' => 2, 'unit_price' => 1.50, 'total' => 3.00, 'category' => 'Groceries', 'subcategory' => 'Dairy'],
                ['name' => 'KELLERBIER', 'quantity' => 1, 'unit_price' => 4.50, 'total' => 4.50, 'category' => 'Beverages', 'subcategory' => null],
            ],
        ]);

        (new ProcessReceipt($receipt))->handle(app(LlmService::class));

        $receipt->refresh();
        $this->assertSame('processed', $receipt->status);
        $this->assertSame('REWE', $receipt->vendor);
        $this->assertCount(2, $receipt->items);

        // find-or-create: parent + nested subcategory under it
        $groceries = Category::where('name', 'Groceries')->whereNull('parent_id')->first();
        $this->assertNotNull($groceries);
        $dairy = Category::where('name', 'Dairy')->where('parent_id', $groceries->id)->first();
        $this->assertNotNull($dairy);

        $milk = $receipt->items->firstWhere('name', 'MILCH');
        $this->assertSame($groceries->id, $milk->category_id);
        $this->assertSame($dairy->id, $milk->subcategory_id);

        // item without subcategory only gets a category
        $beer = $receipt->items->firstWhere('name', 'KELLERBIER');
        $this->assertNotNull($beer->category_id);
        $this->assertNull($beer->subcategory_id);

        Queue::assertPushed(MatchReceiptItems::class);
    }

    public function test_existing_category_is_reused_not_duplicated(): void
    {
        $receipt = $this->fakeReceipt();
        Category::factory()->create(['name' => 'Groceries', 'slug' => 'groceries']);

        $this->mockLlm([
            'is_receipt' => true,
            'vendor' => 'ALDI',
            'items' => [
                ['name' => 'BROT', 'quantity' => 1, 'unit_price' => 2.0, 'total' => 2.0, 'category' => 'Groceries'],
            ],
        ]);

        (new ProcessReceipt($receipt))->handle(app(LlmService::class));

        $this->assertSame(1, Category::where('name', 'Groceries')->whereNull('parent_id')->count());
    }

    public function test_non_receipt_is_marked_processed_with_zeroed_values(): void
    {
        $receipt = $this->fakeReceipt();

        $this->mockLlm([
            'is_receipt' => false,
            'total_amount' => 0,
            'items' => [],
            'notes' => 'Not a receipt',
        ]);

        (new ProcessReceipt($receipt))->handle(app(LlmService::class));

        $receipt->refresh();
        $this->assertSame('processed', $receipt->status);
        $this->assertNull($receipt->vendor);
        $this->assertSame('0.00', (string) $receipt->total_amount);
        $this->assertCount(0, $receipt->items);

        Queue::assertNotPushed(MatchReceiptItems::class);
    }
}
