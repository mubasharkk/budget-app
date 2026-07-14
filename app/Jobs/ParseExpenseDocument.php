<?php

namespace App\Jobs;

use App\Models\Expense;
use App\Services\LlmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Read an expense's attached invoice/document with the vision LLM and auto-fill
 * the fields the user left blank (amount/currency/date when the amount is
 * unset, and description from the vendor).
 */
class ParseExpenseDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public Expense $expense) {}

    public function handle(LlmService $llmService): void
    {
        $media = $this->expense->getFirstMedia(Expense::DOCUMENT_COLLECTION);

        if ($media === null) {
            return;
        }

        $result = $llmService->parseReceiptFromFile($media->getPath(), $media->mime_type);

        if (! ($result['success'] ?? false)) {
            Log::warning('Expense document parse failed', [
                'expense_id' => $this->expense->id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);

            return;
        }

        $data = $result['data'] ?? [];

        if (! ($data['is_receipt'] ?? false)) {
            Log::info('Expense document is not a receipt; leaving fields as entered', [
                'expense_id' => $this->expense->id,
            ]);

            return;
        }

        $updates = [];

        // Amount left blank (stored as 0) means "read everything from the document".
        if ((float) $this->expense->amount === 0.0) {
            if (isset($data['total_amount']) && is_numeric($data['total_amount'])) {
                $updates['amount'] = $data['total_amount'];
            }

            if (! empty($data['currency'])) {
                $updates['currency'] = $data['currency'];
            }

            if (! empty($data['receipt_date'])) {
                $updates['spent_on'] = $data['receipt_date'];
            }
        }

        if (trim((string) $this->expense->description) === '' && ! empty($data['vendor'])) {
            $updates['description'] = $data['vendor'];
        }

        if ($updates !== []) {
            $this->expense->update($updates);

            Log::info('Expense auto-filled from document', [
                'expense_id' => $this->expense->id,
                'fields' => array_keys($updates),
            ]);
        }
    }
}
