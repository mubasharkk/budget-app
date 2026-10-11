<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_owner_attaches_views_and_removes_documents_without_touching_the_receipt(): void
    {
        $receipt = Receipt::factory()->create(['total_amount' => 42.5, 'status' => 'processed']);
        $this->actingAs($receipt->user);

        $this->post(route('receipts.attachments.store', $receipt), [
            'files' => [
                UploadedFile::fake()->create('bank-transfer.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->image('delivery-note.jpg'),
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $attachments = $receipt->attachments()->get();
        $this->assertSame(['bank-transfer.pdf', 'delivery-note.jpg'], $attachments->pluck('name')->all());
        $this->assertEquals(42.5, $receipt->fresh()->total_amount);
        $this->assertSame(0, $receipt->items()->count());

        $this->get(route('receipts.show', $receipt))
            ->assertInertia(fn ($page) => $page
                ->has('attachments', 2)
                ->where('attachments.0.name', 'bank-transfer.pdf')
                ->where('attachments.0.url', route('receipts.attachments.show', [$receipt, $attachments[0]])));

        $this->get(route('receipts.attachments.show', [$receipt, $attachments[0]]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="bank-transfer.pdf"');

        $this->delete(route('receipts.attachments.destroy', [$receipt, $attachments[0]]))->assertRedirect();
        $this->assertSame(['delivery-note.jpg'], $receipt->attachments()->pluck('name')->all());
    }

    public function test_attachment_routes_are_owner_only_and_scoped_to_the_receipt(): void
    {
        $receipt = Receipt::factory()->create();
        $attachment = $receipt->addMedia(UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'))
            ->toMediaCollection(Receipt::ATTACHMENTS_COLLECTION);

        $this->actingAs(User::factory()->create());
        $this->post(route('receipts.attachments.store', $receipt), ['files' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])->assertForbidden();
        $this->get(route('receipts.attachments.show', [$receipt, $attachment]))->assertForbidden();
        $this->delete(route('receipts.attachments.destroy', [$receipt, $attachment]))->assertForbidden();

        $ownReceipt = Receipt::factory()->create();
        $this->actingAs($ownReceipt->user)
            ->get(route('receipts.attachments.show', [$ownReceipt, $attachment]))
            ->assertNotFound();

        $this->assertModelExists($attachment);
    }

    public function test_uploads_are_validated_and_capped_per_receipt(): void
    {
        $receipt = Receipt::factory()->create();
        $this->actingAs($receipt->user);

        $this->post(route('receipts.attachments.store', $receipt), ['files' => [UploadedFile::fake()->create('script.exe', 10)]])
            ->assertSessionHasErrors(['files.0' => 'Documents must be a photo, PDF, text, CSV, Word, Excel or email file.']);

        foreach (range(1, 10) as $i) {
            $receipt->addMedia(UploadedFile::fake()->create("doc{$i}.pdf", 1, 'application/pdf'))
                ->toMediaCollection(Receipt::ATTACHMENTS_COLLECTION);
        }

        $this->post(route('receipts.attachments.store', $receipt), ['files' => [UploadedFile::fake()->create('one-more.pdf', 1, 'application/pdf')]])
            ->assertSessionHasErrors(['files' => 'A receipt can have at most 10 attached documents.']);
    }

    public function test_deleting_the_receipt_removes_its_attachments(): void
    {
        $receipt = Receipt::factory()->create();
        $attachment = $receipt->addMedia(UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'))
            ->toMediaCollection(Receipt::ATTACHMENTS_COLLECTION);
        $path = $attachment->getPath();

        $this->actingAs($receipt->user)->delete(route('receipts.destroy', $receipt));

        $this->assertModelMissing($attachment);
        $this->assertFileDoesNotExist($path);
    }
}
