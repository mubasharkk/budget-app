<?php

namespace Tests\Feature;

use App\Jobs\ParseExpenseDocument;
use App\Models\Expense;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParseExpenseDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function expenseWithDocument(array $attributes = []): Expense
    {
        $expense = Expense::factory()->create($attributes);
        $expense->addMediaFromString('fake-bytes')
            ->usingFileName('invoice.pdf')
            ->toMediaCollection(Expense::DOCUMENT_COLLECTION);

        return $expense->fresh();
    }

    private function mockLlm(array $data): void
    {
        $this->mock(LlmService::class, function ($mock) use ($data): void {
            $mock->shouldReceive('parseReceiptFromFile')
                ->once()
                ->andReturn(['success' => true, 'data' => $data]);
        });
    }

    public function test_it_fills_blank_fields_from_the_parsed_receipt(): void
    {
        $expense = $this->expenseWithDocument(['amount' => 0, 'description' => null, 'currency' => 'EUR']);

        $this->mockLlm([
            'is_receipt' => true,
            'vendor' => 'REWE',
            'currency' => 'USD',
            'total_amount' => 24.90,
            'receipt_date' => '2026-05-20',
        ]);

        (new ParseExpenseDocument($expense))->handle(app(LlmService::class));

        $expense->refresh();
        $this->assertSame('24.90', $expense->amount);
        $this->assertSame('USD', $expense->currency);
        $this->assertSame('2026-05-20', $expense->spent_on->toDateString());
        $this->assertSame('REWE', $expense->description);
    }

    public function test_it_keeps_a_user_entered_amount_and_only_fills_a_blank_description(): void
    {
        $expense = $this->expenseWithDocument([
            'amount' => 50,
            'currency' => 'EUR',
            'description' => null,
            'spent_on' => '2026-01-01',
        ]);

        $this->mockLlm([
            'is_receipt' => true,
            'vendor' => 'REWE',
            'currency' => 'USD',
            'total_amount' => 24.90,
            'receipt_date' => '2026-05-20',
        ]);

        (new ParseExpenseDocument($expense))->handle(app(LlmService::class));

        $expense->refresh();
        $this->assertSame('50.00', $expense->amount);
        $this->assertSame('EUR', $expense->currency);
        $this->assertSame('2026-01-01', $expense->spent_on->toDateString());
        $this->assertSame('REWE', $expense->description);
    }

    public function test_it_leaves_fields_untouched_when_the_document_is_not_a_receipt(): void
    {
        $expense = $this->expenseWithDocument(['amount' => 0, 'description' => null]);

        $this->mockLlm(['is_receipt' => false]);

        (new ParseExpenseDocument($expense))->handle(app(LlmService::class));

        $expense->refresh();
        $this->assertSame('0.00', $expense->amount);
        $this->assertNull($expense->description);
    }
}
