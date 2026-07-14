<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class MigrateReceiptsToMediaLibrary extends Command
{
    protected $signature = 'receipts:migrate-to-media-library
        {--dry-run : Report what would happen without copying any files}
        {--chunk=200 : Number of receipts to process per chunk}';

    protected $description = 'Copy legacy public-disk receipt files into the media-library receipt collection';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $prefix = $dryRun ? '[DRY RUN] ' : '';

        $total = $this->pendingQuery()->count();

        if ($total === 0) {
            $this->info('No receipts require migration.');

            return self::SUCCESS;
        }

        $this->info("{$prefix}Migrating {$total} legacy receipt file(s) to media-library...");

        $migrated = 0;
        $missing = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $this->pendingQuery()->chunkById($chunk, function ($receipts) use ($dryRun, $bar, &$migrated, &$missing, &$failed): void {
            foreach ($receipts as $receipt) {
                $bar->advance();

                $path = $receipt->stored_path;

                if (! Storage::disk('public')->exists($path)) {
                    $missing++;
                    $this->warn(" Missing file for receipt {$receipt->id}: {$path}");

                    continue;
                }

                if ($dryRun) {
                    $migrated++;

                    continue;
                }

                try {
                    $receipt->addMediaFromDisk($path, 'public')
                        ->preservingOriginal()
                        ->usingFileName(basename($path))
                        ->usingName($receipt->original_filename ?: basename($path))
                        ->toMediaCollection(Receipt::RECEIPT_COLLECTION);

                    $migrated++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error(" Failed receipt {$receipt->id}: {$e->getMessage()}");
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("{$prefix}Done. Migrated: {$migrated}, missing files: {$missing}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Legacy receipts that still need their file copied into media-library.
     */
    private function pendingQuery(): Builder
    {
        return Receipt::query()
            ->whereNotNull('stored_path')
            ->whereDoesntHave('media', fn (Builder $query) => $query->where('collection_name', Receipt::RECEIPT_COLLECTION));
    }
}
