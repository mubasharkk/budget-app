<?php

namespace App\Console\Commands;

use App\Domain\Deals\Exceptions\DealSourceUnavailable;
use App\Domain\Deals\Services\DealSyncService;
use App\Jobs\SyncDealsForPostalCode;
use Illuminate\Console\Command;

class SyncDeals extends Command
{
    protected $signature = 'deals:sync
        {--postal-code=* : Sync only these postal codes (default: every postal code users have saved)}
        {--now : Run in this process instead of queueing one job per postal code}';

    protected $description = 'Store current supermarket offers for every postal code in use and prune expired ones';

    public function handle(DealSyncService $dealSyncService): int
    {
        $pruned = $dealSyncService->pruneExpired();
        $this->line("Pruned {$pruned} expired offers.");

        $postalCodes = collect((array) $this->option('postal-code'))
            ->map(fn ($code): string => trim((string) $code))
            ->filter(fn (string $code): bool => preg_match('/^\d{5}$/', $code) === 1)
            ->unique()
            ->values();

        if ($postalCodes->isEmpty()) {
            $postalCodes = $dealSyncService->postalCodesInUse();
        }

        if ($postalCodes->isEmpty()) {
            $this->info('No postal codes to sync.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($postalCodes as $postalCode) {
            if (! $this->option('now')) {
                SyncDealsForPostalCode::dispatch($postalCode);
                $this->line("Queued {$postalCode}");

                continue;
            }

            try {
                $this->line("{$postalCode}: ".$dealSyncService->syncPostalCode($postalCode).' offers');
            } catch (DealSourceUnavailable $exception) {
                $failed++;
                $this->error("{$postalCode}: {$exception->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
