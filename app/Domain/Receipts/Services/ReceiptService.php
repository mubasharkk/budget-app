<?php

namespace App\Domain\Receipts\Services;

use App\Domain\Receipts\Data\ReceiptListFilters;
use App\Domain\Receipts\Exceptions\ReceiptCannotBeRetried;
use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class ReceiptService
{
    private const FILE_URL_ATTRIBUTES = ['file_url', 'public_file_url', 'direct_file_url'];

    public function paginate(int $userId, ReceiptListFilters $filters): LengthAwarePaginator
    {
        $receipts = Receipt::query()
            ->with(['items.category', 'items.subcategory'])
            ->where('user_id', $userId)
            ->when($filters->status, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters->search, function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('vendor', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%");
                });
            })
            ->orderBy($filters->sort, $filters->direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        $receipts->getCollection()->each(fn (Receipt $receipt) => $receipt->append(self::FILE_URL_ATTRIBUTES));

        return $receipts;
    }

    /**
     * Newest-first listing with item counts, as consumed by the mobile API.
     */
    public function paginateForApi(int $userId, int $perPage): LengthAwarePaginator
    {
        return Receipt::query()
            ->where('user_id', $userId)
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Load items and file URLs for a single-receipt view.
     */
    public function loadForDisplay(Receipt $receipt): Receipt
    {
        return $receipt
            ->load(['items.category', 'items.subcategory'])
            ->append(self::FILE_URL_ATTRIBUTES);
    }

    /**
     * Apply manual corrections; when `items` is given the line items are replaced wholesale.
     *
     * @param  array{vendor?: ?string, receipt_number?: ?string, currency?: ?string, total_amount: numeric, receipt_date: string, items?: ?array<int, array<string, mixed>>}  $data
     */
    public function update(Receipt $receipt, array $data): Receipt
    {
        DB::transaction(function () use ($receipt, $data): void {
            $receipt->update([
                'vendor' => $data['vendor'] ?? null,
                'receipt_number' => $data['receipt_number'] ?? null,
                'currency' => $data['currency'] ?? $receipt->currency,
                'total_amount' => $data['total_amount'],
                'receipt_date' => $data['receipt_date'],
            ]);

            if (! is_array($data['items'] ?? null)) {
                return;
            }

            $receipt->items()->delete();

            foreach ($data['items'] as $item) {
                $receipt->items()->create([
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total'],
                    'category_id' => $item['category_id'] ?? null,
                    'subcategory_id' => $item['subcategory_id'] ?? null,
                ]);
            }
        });

        return $receipt;
    }

    /**
     * Reset a failed receipt to pending and queue it for processing again.
     *
     * @throws ReceiptCannotBeRetried
     */
    public function retry(Receipt $receipt): void
    {
        if (! $receipt->isFailed()) {
            throw ReceiptCannotBeRetried::notFailed($receipt);
        }

        $receipt->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        ProcessReceipt::dispatch($receipt);
    }

    /**
     * The stored receipt document, or null when it is missing on disk.
     */
    public function storedFile(Receipt $receipt): ?Media
    {
        $media = $receipt->getFirstMedia(Receipt::RECEIPT_COLLECTION);

        if ($media === null || ! file_exists($media->getPath())) {
            return null;
        }

        return $media;
    }

    /**
     * Delete the receipt; media-library removes the attached file and items cascade.
     *
     * @throws Throwable
     */
    public function delete(Receipt $receipt): void
    {
        Log::info('Deleting receipt', [
            'receipt_id' => $receipt->id,
            'user_id' => $receipt->user_id,
            'filename' => $receipt->original_filename,
        ]);

        try {
            $receipt->delete();
        } catch (Throwable $e) {
            Log::error('Failed to delete receipt', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        Log::info('Receipt deleted successfully', ['receipt_id' => $receipt->id]);
    }
}
