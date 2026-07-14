<?php

namespace Tests\Feature;

use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RelinkOrphanedReceiptFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function legacyPath(Receipt $receipt, string $extension = 'png'): string
    {
        return 'receipts/'.$receipt->created_at->year.'/'.$receipt->created_at->format('m').'/'.Str::uuid().".{$extension}";
    }

    public function test_it_relinks_a_receipt_to_its_file_by_size_and_type(): void
    {
        $content = str_repeat('a', 25);
        $receipt = Receipt::factory()->create(['file_type' => 'png', 'file_size' => 25]);
        $path = $this->legacyPath($receipt);
        Storage::disk('public')->put($path, $content);

        $this->artisan('receipts:relink-orphaned-files')->assertSuccessful();

        $media = $receipt->fresh()->getFirstMedia(Receipt::RECEIPT_COLLECTION);
        $this->assertNotNull($media);
        $this->assertSame('local', $media->disk);

        // Original file must be preserved on the public disk.
        Storage::disk('public')->assertExists($path);
    }

    public function test_it_breaks_size_ties_using_the_created_month(): void
    {
        $receipt = Receipt::factory()->create([
            'file_type' => 'png',
            'file_size' => 40,
            'created_at' => now(),
        ]);

        // Two same-size/type files; only one lives in the receipt's created month.
        $matchingPath = $this->legacyPath($receipt);
        $otherMonth = now()->subMonths(3);
        $decoyPath = 'receipts/'.$otherMonth->year.'/'.$otherMonth->format('m').'/'.Str::uuid().'.png';

        Storage::disk('public')->put($matchingPath, str_repeat('b', 40));
        Storage::disk('public')->put($decoyPath, str_repeat('c', 40));

        $this->artisan('receipts:relink-orphaned-files')->assertSuccessful();

        $media = $receipt->fresh()->getFirstMedia(Receipt::RECEIPT_COLLECTION);
        $this->assertNotNull($media);
        $this->assertSame(basename($matchingPath), $media->file_name);
    }

    public function test_dry_run_attaches_nothing(): void
    {
        $receipt = Receipt::factory()->create(['file_type' => 'png', 'file_size' => 12]);
        Storage::disk('public')->put($this->legacyPath($receipt), str_repeat('a', 12));

        $this->artisan('receipts:relink-orphaned-files', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($receipt->fresh()->getFirstMedia(Receipt::RECEIPT_COLLECTION));
    }

    public function test_receipt_with_no_matching_file_is_left_unlinked(): void
    {
        // A file exists so the index is non-empty, but its size does not match.
        $receipt = Receipt::factory()->create(['file_type' => 'png', 'file_size' => 999]);
        Storage::disk('public')->put($this->legacyPath($receipt), str_repeat('a', 10));

        $this->artisan('receipts:relink-orphaned-files')->assertSuccessful();

        $this->assertNull($receipt->fresh()->getFirstMedia(Receipt::RECEIPT_COLLECTION));
    }
}
