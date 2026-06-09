<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_obtain_api_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'iphone',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_user_can_upload_receipt_via_api(): void
    {
        Queue::fake();
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/receipts', [
            'files' => [
                UploadedFile::fake()->image('receipt.jpg'),
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('receipts.0.status', 'pending');

        $this->assertDatabaseCount('receipts', 1);
        Queue::assertPushed(ProcessReceipt::class);
    }

    public function test_user_can_list_their_receipts_via_api(): void
    {
        $user = User::factory()->create();
        Receipt::factory()->count(2)->for($user)->create();
        Receipt::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/receipts')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_view_another_users_receipt_via_api(): void
    {
        $user = User::factory()->create();
        $otherReceipt = Receipt::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/receipts/{$otherReceipt->id}")
            ->assertForbidden();
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/receipts')->assertUnauthorized();
    }
}
