<?php

namespace App\Console\Commands;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use Illuminate\Console\Command;

class ReprocessReceipts extends Command
{
    protected $signature = 'receipts:reprocess
        {--user= : Only reprocess receipts for the given user id}
        {--missing-number : Only reprocess receipts that have no receipt_number}
        {--status= : Only reprocess receipts with the given status}';

    protected $description = 'Re-run the parsing pipeline over existing receipts (e.g. to backfill receipt numbers)';

    public function handle(): int
    {
        $query = Receipt::query()
            ->when($this->option('user'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($this->option('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($this->option('missing-number'), fn ($q) => $q->whereNull('receipt_number'));

        $total = $query->count();

        if ($total === 0) {
            $this->info('No receipts matched the given filters.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $query->chunkById(200, function ($receipts) use (&$dispatched): void {
            foreach ($receipts as $receipt) {
                ProcessReceipt::dispatch($receipt);
                $dispatched++;
            }
        });

        $this->info("Dispatched {$dispatched} receipt(s) for reprocessing. Ensure a queue worker is running.");

        return self::SUCCESS;
    }
}