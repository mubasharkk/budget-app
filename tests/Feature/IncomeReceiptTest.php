<?php

namespace Tests\Feature;

use App\Domain\Analytics\Services\ExpenseService;
use App\Domain\Shared\Services\LlmService;
use App\Enums\ReceiptKind;
use App\Jobs\MatchReceiptItems;
use App\Jobs\ProcessReceipt;
use App\Models\Income;
use App\Models\Receipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomeReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('local');
    }

    private function incomeReceipt(User $user): Receipt
    {
        $receipt = Receipt::factory()->for($user)->pending()->create([
            'mime' => 'image/png',
            'kind' => ReceiptKind::Income,
        ]);
        $receipt->addMediaFromString('fake-image-bytes')
            ->usingFileName('payslip.png')
            ->toMediaCollection(Receipt::RECEIPT_COLLECTION);

        return $receipt;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function process(Receipt $receipt, array $data): void
    {
        $this->mock(LlmService::class, function ($mock) use ($data): void {
            $mock->shouldReceive('parseReceiptFromFile')
                ->withArgs(fn (string $path, string $mime, bool $isIncome): bool => $isIncome)
                ->andReturn(['success' => true, 'data' => $data]);
        });

        (new ProcessReceipt($receipt->fresh()))->handle(app(LlmService::class));
    }

    public function test_upload_can_be_marked_as_income(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('receipts.store'), [
                'files' => [UploadedFile::fake()->image('payslip.jpg')],
                'kind' => 'income',
            ])
            ->assertRedirect(route('receipts.index'));

        $this->assertSame(ReceiptKind::Income, Receipt::query()->sole()->kind);
    }

    public function test_uploads_are_expenses_by_default(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('receipts.store'), [
            'files' => [UploadedFile::fake()->image('receipt.jpg')],
        ]);

        $this->assertSame(ReceiptKind::Expense, Receipt::query()->sole()->kind);
    }

    public function test_processing_an_income_receipt_records_one_time_income(): void
    {
        $user = User::factory()->create();
        $receipt = $this->incomeReceipt($user);

        $this->process($receipt, [
            'is_receipt' => true,
            'vendor' => 'Acme GmbH',
            'currency' => 'EUR',
            'total_amount' => 2450.75,
            'receipt_date' => '2026-06-28',
            'items' => [['name' => 'Salary', 'quantity' => 1, 'unit_price' => 2450.75, 'total' => 2450.75, 'category' => 'Other']],
        ]);

        $income = Income::query()->sole();
        $this->assertSame($user->id, $income->user_id);
        $this->assertSame($receipt->id, $income->receipt_id);
        $this->assertEquals(2450.75, $income->amount);
        $this->assertSame('Acme GmbH', $income->source);
        $this->assertSame('2026-06-28', $income->received_on->toDateString());

        $this->assertSame('processed', $receipt->fresh()->status);
        $this->assertSame(0, $receipt->items()->count());
        Queue::assertNotPushed(MatchReceiptItems::class);
    }

    public function test_reprocessing_updates_the_same_income_entry(): void
    {
        $user = User::factory()->create();
        $receipt = $this->incomeReceipt($user);

        $this->process($receipt, ['is_receipt' => true, 'vendor' => 'Acme', 'total_amount' => 100, 'receipt_date' => '2026-06-01']);
        $this->process($receipt, ['is_receipt' => true, 'vendor' => 'Acme', 'total_amount' => 120, 'receipt_date' => '2026-06-01']);

        $this->assertEquals(120, Income::query()->sole()->amount);
    }

    public function test_income_receipts_are_not_counted_as_spending(): void
    {
        $user = User::factory()->create();
        Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-10', 'total_amount' => 40]);
        Receipt::factory()->for($user)->create([
            'receipt_date' => '2026-06-11',
            'total_amount' => 2000,
            'kind' => ReceiptKind::Income,
        ]);

        $total = app(ExpenseService::class)->variableTotal(
            $user->id,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        $this->assertEquals(40.0, $total);
    }

    public function test_editing_or_deleting_the_receipt_keeps_the_income_in_step(): void
    {
        $user = User::factory()->create();
        $receipt = $this->incomeReceipt($user);
        $this->process($receipt, ['is_receipt' => true, 'vendor' => 'Acme', 'total_amount' => 100, 'receipt_date' => '2026-06-01']);

        $this->actingAs($user)
            ->put(route('receipts.update', $receipt), [
                'vendor' => 'Acme Ltd',
                'total_amount' => 150,
                'receipt_date' => '2026-06-02',
            ])
            ->assertRedirect(route('receipts.index'));

        $income = Income::query()->sole();
        $this->assertEquals(150, $income->amount);
        $this->assertSame('Acme Ltd', $income->source);

        $this->actingAs($user)->delete(route('receipts.destroy', $receipt));

        $this->assertDatabaseCount('incomes', 0);
    }
}
