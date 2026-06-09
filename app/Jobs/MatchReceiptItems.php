<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Services\ProductMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MatchReceiptItems implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public Receipt $receipt) {}

    /**
     * Match receipt line items to canonical products via the LLM.
     */
    public function handle(ProductMatchingService $matchingService): void
    {
        if ($this->receipt->status !== 'processed') {
            Log::info('Skipping product matching for non-processed receipt', [
                'receipt_id' => $this->receipt->id,
                'status' => $this->receipt->status,
            ]);

            return;
        }

        try {
            Log::info('Starting product matching', ['receipt_id' => $this->receipt->id]);

            $matchingService->matchReceipt($this->receipt->fresh(['items.category']));

            Log::info('Product matching completed', ['receipt_id' => $this->receipt->id]);
        } catch (\Exception $e) {
            Log::error('Product matching failed', [
                'receipt_id' => $this->receipt->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
