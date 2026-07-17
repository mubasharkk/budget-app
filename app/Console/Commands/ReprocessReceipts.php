<?php

namespace App\Console\Commands;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ReprocessReceipts extends Command
{
    protected $signature = 'receipts:reprocess
        {--user= : Only reprocess receipts for the given user id}
        {--missing-number : Only reprocess receipts that have no receipt_number}
        {--misclassified : Only reprocess receipts previously flagged as not a receipt (ocr_data.is_receipt = false)}
        {--status= : Only reprocess receipts with the given status}
        {--dry-run : List matching receipts without dispatching anything}';

    protected $description = 'Re-run the parsing pipeline over existing receipts (e.g. to re-classify receipts the old prompt got wrong)';

    public function handle(): int
    {
        $query = Receipt::query()
            ->when($this->option('user'), fn (Builder $q, $userId) => $q->where('user_id', $userId))
            ->when($this->option('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($this->option('missing-number'), fn (Builder $q) => $q->whereNull('receipt_number'))
            ->when($this->option('misclassified'), fn (Builder $q) => $q->where('ocr_data->is_receipt', false));

        $total = $query->count();

        if ($total === 0) {
            $this->info('No receipts matched the given filters.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] {$total} receipt(s) would be reprocessed:");
            $query->orderBy('id')->each(function (Receipt $receipt): void {
                $this->line("  #{$receipt->id} status={$receipt->status} vendor=".($receipt->vendor ?? 'null'));
            });

            return self::SUCCESS;
        }

        $dispatched = 0;
        $query->chunkById(200, function ($receipts) use (&$dispatched): void {
            foreach ($receipts as $receipt) {
                $receipt->update(['status' => 'pending', 'error_message' => null]);
                ProcessReceipt::dispatch($receipt);
                $dispatched++;
            }
        });

        $this->info("Dispatched {$dispatched} receipt(s) for reprocessing. Ensure a queue worker is running.");

        return self::SUCCESS;
    }
}
