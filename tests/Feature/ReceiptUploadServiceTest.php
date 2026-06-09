<?php

namespace Tests\Feature;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ReceiptUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_image_and_dispatches_processing_job(): void
    {
        Queue::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('receipt.jpg', 800, 1200);

        $receipt = app(ReceiptUploadService::class)->storeOne($user->id, $file);

        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertSame($user->id, $receipt->user_id);
        $this->assertSame('pending', $receipt->status);
        $this->assertStringEndsWith('.png', $receipt->stored_path);
        Storage::disk('public')->assertExists($receipt->stored_path);

        Queue::assertPushed(ProcessReceipt::class, fn (ProcessReceipt $job): bool => $job->receipt->id === $receipt->id);
    }

    public function test_stores_pdf_without_conversion(): void
    {
        Queue::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $receipt = app(ReceiptUploadService::class)->storeOne($user->id, $file);

        $this->assertSame('pdf', $receipt->file_type);
        $this->assertStringEndsWith('.pdf', $receipt->stored_path);
        Storage::disk('public')->assertExists($receipt->stored_path);
    }

    public function test_store_many_limits_to_five_files(): void
    {
        Queue::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        $files = collect(range(1, 7))
            ->map(fn (): UploadedFile => UploadedFile::fake()->image('receipt.jpg'))
            ->all();

        $receipts = app(ReceiptUploadService::class)->storeMany($user->id, $files);

        $this->assertCount(5, $receipts);
        Queue::assertPushed(ProcessReceipt::class, 5);
    }
}
