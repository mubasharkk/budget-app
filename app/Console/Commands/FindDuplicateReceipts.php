<?php

namespace App\Console\Commands;

use App\Domain\Receipts\Services\ReceiptDuplicateDetector;
use App\Models\Receipt;
use Illuminate\Console\Command;

class FindDuplicateReceipts extends Command
{
    protected $signature = 'receipts:find-duplicates
        {--user= : Only check receipts of the given user id}
        {--mark : Flag the duplicates found so they stop counting towards spending}';

    protected $description = 'List receipts that match an earlier upload by receipt number, datetime, amount and vendor';

    public function handle(ReceiptDuplicateDetector $detector): int
    {
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;
        $matches = $detector->scan($userId);

        if ($matches->isEmpty()) {
            $this->info('No duplicate receipts found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Duplicate', 'Original', 'User', 'Vendor', 'Receipt no.', 'Date', 'Amount', 'Flagged'],
            $matches->map(fn (array $match): array => [
                '#'.$match['duplicate']->id,
                '#'.$match['original']->id,
                $match['duplicate']->user_id,
                $match['duplicate']->vendor,
                $match['duplicate']->receipt_number ?? '—',
                $match['duplicate']->receipt_date?->format('Y-m-d H:i'),
                $match['duplicate']->total_amount.' '.$match['duplicate']->currency,
                $match['duplicate']->duplicate_of_id === $match['original']->id ? 'yes' : 'no',
            ])->all(),
        );

        $this->info("{$matches->count()} duplicate receipt(s) found.");

        if (! $this->option('mark')) {
            $this->line('Run again with --mark to flag them.');

            return self::SUCCESS;
        }

        $matches->each(function (array $match): void {
            /** @var Receipt $duplicate */
            $duplicate = $match['duplicate'];
            $duplicate->update(['duplicate_of_id' => $match['original']->id]);
            $duplicate->income()->delete();
        });

        $this->info("Flagged {$matches->count()} receipt(s) as duplicates.");

        return self::SUCCESS;
    }
}
