<?php

namespace Tests\Feature;

use App\Jobs\ProcessReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_receipt_via_web(): void
    {
        Queue::fake();
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('receipts.store'), [
                'files' => [
                    UploadedFile::fake()->image('receipt.jpg'),
                ],
            ])
            ->assertRedirect(route('receipts.index'));

        $this->assertDatabaseCount('receipts', 1);
        Queue::assertPushed(ProcessReceipt::class);
    }

    public function test_scan_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('receipts.scan'))
            ->assertOk();
    }

    public function test_upload_rejects_invalid_file_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('receipts.store'), [
                'files' => [
                    UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
                ],
            ])
            ->assertSessionHasErrors('files.0');
    }

    public function test_guest_cannot_upload(): void
    {
        $this->post(route('receipts.store'), [
            'files' => [UploadedFile::fake()->image('receipt.jpg')],
        ])->assertRedirect('/login');
    }
}
