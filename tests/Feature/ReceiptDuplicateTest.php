<?php

namespace Tests\Feature;

use App\Domain\Receipts\Services\ReceiptDuplicateDetector;
use App\Domain\Shared\Services\LlmService;
use App\Enums\ReceiptKind;
use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->user = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function receipt(array $overrides = []): Receipt
    {
        return Receipt::factory()->create([
            'user_id' => $this->user->id,
            'vendor' => 'REWE',
            'receipt_number' => '1234',
            'total_amount' => 14.39,
            'receipt_date' => '2026-05-01 14:58:00',
            ...$overrides,
        ]);
    }

    public function test_processing_flags_a_receipt_matching_an_earlier_upload(): void
    {
        $original = $this->receipt();

        Storage::fake('local');
        $upload = Receipt::factory()->pending()->create(['user_id' => $this->user->id]);
        $upload->addMediaFromString('bytes')->usingFileName('r.png')->toMediaCollection(Receipt::RECEIPT_COLLECTION);

        $this->mock(LlmService::class)->shouldReceive('parseReceiptFromFile')->once()->andReturn([
            'success' => true,
            'data' => [
                'is_receipt' => true,
                'vendor' => ' rewe ',
                'receipt_number' => '1234',
                'currency' => 'EUR',
                'total_amount' => 14.39,
                'receipt_date' => '2026-05-01',
                'receipt_time' => '14:58:00',
                'items' => [['name' => 'MILCH', 'quantity' => 1, 'unit_price' => 14.39, 'total' => 14.39, 'category' => 'Groceries']],
            ],
        ]);

        (new ProcessReceipt($upload))->handle(app(LlmService::class));

        $this->assertSame($original->id, $upload->fresh()->duplicate_of_id);
        $this->assertSame(0, Receipt::expenses()->where('id', $upload->id)->count());
    }

    public function test_receipts_differing_in_number_date_amount_or_vendor_are_not_duplicates(): void
    {
        $this->receipt();
        $detector = app(ReceiptDuplicateDetector::class);

        $this->assertNull($detector->findOriginal($this->receipt(['receipt_number' => '9999'])));
        $this->assertNull($detector->findOriginal($this->receipt(['receipt_date' => '2026-05-01 15:00:00'])));
        $this->assertNull($detector->findOriginal($this->receipt(['total_amount' => 14.40])));
        $this->assertNull($detector->findOriginal($this->receipt(['vendor' => 'ALDI'])));
        $this->assertNull($detector->findOriginal($this->receipt(['kind' => ReceiptKind::Income])));
        $this->assertNull($detector->findOriginal(Receipt::factory()->create([
            'vendor' => 'REWE', 'receipt_number' => '1234', 'total_amount' => 14.39, 'receipt_date' => '2026-05-01 14:58:00',
        ])));
    }

    public function test_a_missing_receipt_number_matches_on_the_other_fields(): void
    {
        $original = $this->receipt(['receipt_number' => null]);

        $this->assertSame($original->id, app(ReceiptDuplicateDetector::class)->findOriginal($this->receipt())?->id);
    }

    public function test_user_sees_the_duplicate_and_can_keep_it(): void
    {
        $original = $this->receipt();
        $duplicate = $this->receipt(['duplicate_of_id' => $original->id]);

        $this->actingAs($this->user)->get(route('receipts.index'))
            ->assertInertia(fn ($page) => $page
                ->has('duplicateReceipts', 1)
                ->where('duplicateReceipts.0.id', $duplicate->id)
                ->where('duplicateReceipts.0.duplicate_of_id', $original->id));

        $this->actingAs($this->user)
            ->patch(route('receipts.keep-duplicate', $duplicate))
            ->assertRedirect();

        $duplicate->refresh();
        $this->assertNull($duplicate->duplicate_of_id);
        $this->assertNotNull($duplicate->duplicate_ignored_at);
        $this->assertNull(app(ReceiptDuplicateDetector::class)->flag($duplicate));
    }

    public function test_other_users_cannot_keep_a_duplicate(): void
    {
        $original = $this->receipt();
        $duplicate = $this->receipt(['duplicate_of_id' => $original->id]);

        $this->actingAs(User::factory()->create())
            ->patch(route('receipts.keep-duplicate', $duplicate))
            ->assertForbidden();
    }

    public function test_command_lists_and_marks_duplicates(): void
    {
        $original = $this->receipt();
        $duplicate = $this->receipt();

        $this->artisan('receipts:find-duplicates')
            ->expectsOutputToContain('1 duplicate receipt(s) found.')
            ->assertSuccessful();
        $this->assertNull($duplicate->fresh()->duplicate_of_id);

        $this->artisan('receipts:find-duplicates', ['--mark' => true])->assertSuccessful();
        $this->assertSame($original->id, $duplicate->fresh()->duplicate_of_id);
    }

    public function test_discarding_a_duplicate_from_the_dashboard_returns_there(): void
    {
        $original = $this->receipt();
        $duplicate = $this->receipt(['duplicate_of_id' => $original->id]);

        $this->actingAs($this->user)
            ->from(route('dashboard'))
            ->delete(route('receipts.destroy', $duplicate))
            ->assertRedirect(route('dashboard'));

        $this->assertModelMissing($duplicate);
        $this->assertModelExists($original);
    }
}
