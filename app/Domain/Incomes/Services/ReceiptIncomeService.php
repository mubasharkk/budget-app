<?php

namespace App\Domain\Incomes\Services;

use App\Models\Income;
use App\Models\Receipt;

class ReceiptIncomeService
{
    /**
     * Keep the one-time income entry for an income receipt in step with the receipt:
     * created on first processing, updated on retries and manual corrections, and
     * removed when the receipt no longer carries a positive amount or duplicates
     * an earlier upload.
     */
    public function syncFromReceipt(Receipt $receipt): ?Income
    {
        if (! $receipt->isIncome()) {
            return null;
        }

        if ((float) $receipt->total_amount <= 0 || $receipt->isDuplicate()) {
            $receipt->income()->delete();

            return null;
        }

        $income = Income::query()->firstOrNew(['receipt_id' => $receipt->id]);

        if (! $income->exists) {
            $income->notes = 'Added from uploaded receipt #'.$receipt->id;
        }

        $income->fill([
            'user_id' => $receipt->user_id,
            'amount' => $receipt->total_amount,
            'currency' => $receipt->currency,
            'received_on' => ($receipt->receipt_date ?? $receipt->created_at)->toDateString(),
            'source' => $receipt->vendor ?: $receipt->original_filename,
        ])->save();

        return $income;
    }
}
