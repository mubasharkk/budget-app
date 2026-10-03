<?php

namespace App\Domain\Receipts\Services;

use App\Models\Receipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Spots a receipt the user has already uploaded. Two receipts match when they belong
 * to the same user and kind and share vendor (case-insensitive), receipt datetime and
 * total amount; when both carry a receipt number it must match too. The oldest receipt
 * of a matching set is the original, every later one a duplicate of it.
 */
class ReceiptDuplicateDetector
{
    /**
     * The earlier receipt this one duplicates, or null when it is unique or lacks the
     * vendor, date or amount needed to compare it.
     */
    public function findOriginal(Receipt $receipt): ?Receipt
    {
        if (! $this->isComparable($receipt)) {
            return null;
        }

        return Receipt::query()
            ->where('user_id', $receipt->user_id)
            ->where('kind', $receipt->kind)
            ->where('id', '<', $receipt->id)
            ->where('status', 'processed')
            ->whereRaw('LOWER(TRIM(vendor)) = ?', [$this->normalizeVendor($receipt->vendor)])
            ->where('receipt_date', $receipt->receipt_date)
            ->where('total_amount', $receipt->total_amount)
            ->when(filled($receipt->receipt_number), function (Builder $query) use ($receipt): void {
                $query->where(function (Builder $inner) use ($receipt): void {
                    $inner->whereNull('receipt_number')
                        ->orWhere('receipt_number', '')
                        ->orWhere('receipt_number', trim($receipt->receipt_number));
                });
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * Flag (or un-flag) the receipt against its original and return the original.
     * Receipts the user already chose to keep are never flagged again.
     */
    public function flag(Receipt $receipt): ?Receipt
    {
        $original = $receipt->duplicate_ignored_at === null ? $this->findOriginal($receipt) : null;

        $receipt->update(['duplicate_of_id' => $original?->id]);

        return $original;
    }

    /**
     * Every processed receipt that duplicates an earlier one, paired with its original.
     *
     * @return Collection<int, array{duplicate: Receipt, original: Receipt}>
     */
    public function scan(?int $userId = null): Collection
    {
        $matches = collect();

        Receipt::query()
            ->where('status', 'processed')
            ->whereNotNull('vendor')
            ->whereNotNull('receipt_date')
            ->whereNotNull('total_amount')
            ->whereNull('duplicate_ignored_at')
            ->when($userId, fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->orderBy('id')
            ->chunkById(500, function (Collection $receipts) use ($matches): void {
                foreach ($receipts as $receipt) {
                    $original = $this->findOriginal($receipt);

                    if ($original !== null) {
                        $matches->push(['duplicate' => $receipt, 'original' => $original]);
                    }
                }
            });

        return $matches;
    }

    /**
     * Receipts held back as duplicates that still await the user's decision.
     *
     * @return Collection<int, Receipt>
     */
    public function awaitingDecision(int $userId, int $limit = 20): Collection
    {
        return Receipt::query()
            ->where('user_id', $userId)
            ->whereNotNull('duplicate_of_id')
            ->with('duplicateOf:id,original_filename,vendor')
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'original_filename', 'vendor', 'total_amount', 'receipt_date', 'duplicate_of_id']);
    }

    private function isComparable(Receipt $receipt): bool
    {
        return filled($receipt->vendor)
            && $receipt->receipt_date !== null
            && $receipt->total_amount !== null
            && (float) $receipt->total_amount > 0;
    }

    private function normalizeVendor(string $vendor): string
    {
        return mb_strtolower(trim($vendor));
    }
}
