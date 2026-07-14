<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_receipt(): void
    {
        $owner = User::factory()->create();
        $receipt = Receipt::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->get(route('receipts.show', $receipt))
            ->assertOk();
    }

    public function test_non_owner_cannot_view_receipt(): void
    {
        $receipt = Receipt::factory()->for(User::factory())->create();

        $this->actingAs(User::factory()->create())
            ->get(route('receipts.show', $receipt))
            ->assertForbidden();
    }

    public function test_non_owner_cannot_delete_receipt(): void
    {
        $receipt = Receipt::factory()->for(User::factory())->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('receipts.destroy', $receipt))
            ->assertForbidden();

        $this->assertDatabaseHas('receipts', ['id' => $receipt->id]);
    }

    public function test_owner_can_download_media_backed_receipt_file_but_others_cannot(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $receipt = Receipt::factory()->for($owner)->create();
        $receipt->addMedia(UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'))
            ->toMediaCollection(Receipt::RECEIPT_COLLECTION);

        $this->actingAs($owner)
            ->get(route('receipts.file', $receipt))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('receipts.file', $receipt))
            ->assertForbidden();
    }

    public function test_non_owner_cannot_update_receipt(): void
    {
        $receipt = Receipt::factory()->for(User::factory())->create();

        $this->actingAs(User::factory()->create())
            ->put(route('receipts.update', $receipt), [
                'total_amount' => 5,
                'receipt_date' => now()->subDay()->toDateString(),
            ])
            ->assertForbidden();
    }
}
