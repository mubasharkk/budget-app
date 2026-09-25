<?php

namespace Tests\Feature;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReceiptManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_update_replaces_line_items(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create();
        ReceiptItem::factory()->count(2)->for($receipt)->create();

        $this->actingAs($user)
            ->put(route('receipts.update', $receipt), [
                'vendor' => 'Aldi',
                'total_amount' => 3.5,
                'receipt_date' => now()->subDay()->toDateString(),
                'items' => [
                    ['name' => 'Milk', 'quantity' => 2, 'unit_price' => 1.75, 'total' => 3.5],
                ],
            ])
            ->assertRedirect(route('receipts.index'));

        $receipt->refresh();
        $this->assertSame('Aldi', $receipt->vendor);
        $this->assertSame(['Milk'], $receipt->items()->pluck('name')->all());
    }

    public function test_update_without_items_keeps_existing_line_items(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create();
        ReceiptItem::factory()->count(2)->for($receipt)->create();

        $this->actingAs($user)
            ->put(route('receipts.update', $receipt), [
                'total_amount' => 10,
                'receipt_date' => now()->subDay()->toDateString(),
            ])
            ->assertRedirect(route('receipts.index'));

        $this->assertSame(2, $receipt->items()->count());
    }

    public function test_retry_requeues_a_failed_receipt(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->failed()->create();

        $this->actingAs($user)
            ->post(route('receipts.retry', $receipt))
            ->assertSessionHas('success');

        $this->assertSame('pending', $receipt->fresh()->status);
        $this->assertNull($receipt->fresh()->error_message);
        Queue::assertPushed(ProcessReceipt::class);
    }

    public function test_retry_rejects_a_receipt_that_has_not_failed(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create(['status' => 'processed']);

        $this->actingAs($user)
            ->post(route('receipts.retry', $receipt))
            ->assertSessionHas('error', 'Only failed receipts can be retried.');

        $this->assertSame('processed', $receipt->fresh()->status);
        Queue::assertNothingPushed();
    }
}
