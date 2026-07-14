<?php

namespace Tests\Feature;

use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateReceiptsToMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_it_copies_a_legacy_file_into_media_library_without_removing_the_original(): void
    {
        $receipt = Receipt::factory()->create();
        Storage::disk('public')->put($receipt->stored_path, 'fake-bytes');

        $this->artisan('receipts:migrate-to-media-library')->assertSuccessful();

        $media = $receipt->fresh()->getFirstMedia(Receipt::RECEIPT_COLLECTION);
        $this->assertNotNull($media);
        $this->assertSame('local', $media->disk);

        // preservingOriginal() must leave the legacy file in place.
        Storage::disk('public')->assertExists($receipt->stored_path);
    }

    public function test_it_is_idempotent(): void
    {
        $receipt = Receipt::factory()->create();
        Storage::disk('public')->put($receipt->stored_path, 'fake-bytes');

        $this->artisan('receipts:migrate-to-media-library')->assertSuccessful();
        $this->artisan('receipts:migrate-to-media-library')
            ->expectsOutputToContain('No receipts require migration')
            ->assertSuccessful();

        $this->assertCount(1, $receipt->fresh()->getMedia(Receipt::RECEIPT_COLLECTION));
    }

    public function test_it_skips_receipts_whose_file_is_missing(): void
    {
        $receipt = Receipt::factory()->create(); // stored_path set, but no file on disk

        $this->artisan('receipts:migrate-to-media-library')->assertSuccessful();

        $this->assertNull($receipt->fresh()->getFirstMedia(Receipt::RECEIPT_COLLECTION));
    }

    public function test_dry_run_does_not_copy_anything(): void
    {
        $receipt = Receipt::factory()->create();
        Storage::disk('public')->put($receipt->stored_path, 'fake-bytes');

        $this->artisan('receipts:migrate-to-media-library', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($receipt->fresh()->getFirstMedia(Receipt::RECEIPT_COLLECTION));
    }
}
