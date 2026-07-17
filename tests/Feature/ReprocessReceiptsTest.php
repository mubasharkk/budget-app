<?php

namespace Tests\Feature;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReprocessReceiptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_misclassified_filter_reprocesses_only_flagged_receipts(): void
    {
        Queue::fake();

        $misclassified = Receipt::factory()->create([
            'status' => 'processed',
            'ocr_data' => ['is_receipt' => false, 'notes' => 'Document is not a receipt or invoice'],
        ]);
        $genuine = Receipt::factory()->create([
            'status' => 'processed',
            'ocr_data' => ['is_receipt' => true],
        ]);

        $this->artisan('receipts:reprocess', ['--misclassified' => true])
            ->assertSuccessful();

        Queue::assertPushed(ProcessReceipt::class, 1);
        Queue::assertPushed(fn (ProcessReceipt $job) => $job->receipt->is($misclassified));

        $this->assertSame('pending', $misclassified->fresh()->status);
        $this->assertSame('processed', $genuine->fresh()->status);
    }

    public function test_dry_run_dispatches_nothing(): void
    {
        Queue::fake();

        $receipt = Receipt::factory()->create([
            'status' => 'processed',
            'ocr_data' => ['is_receipt' => false],
        ]);

        $this->artisan('receipts:reprocess', ['--misclassified' => true, '--dry-run' => true])
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame('processed', $receipt->fresh()->status);
    }
}
