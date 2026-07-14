<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Recovery command for receipts whose legacy stored_path/original_path columns
 * were dropped before their files were copied into media-library.
 *
 * It re-matches each media-less receipt to a file still sitting on the public
 * disk under receipts/{year}/{month}/, keyed on the surviving file_size +
 * file_type columns, using the receipt's created month to break size ties.
 * Matches are copied into the media-library collection (originals preserved).
 */
class RelinkOrphanedReceiptFiles extends Command
{
    protected $signature = 'receipts:relink-orphaned-files
        {--dry-run : Report matches without copying anything}
        {--disk=public : Disk that still holds the legacy receipt files}';

    protected $description = 'Re-attach receipts to their legacy files on disk after the stored_path column was dropped';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = (string) $this->option('disk');
        $prefix = $dryRun ? '[DRY RUN] ' : '';

        $index = $this->indexLegacyFiles($disk);

        if ($index === []) {
            $this->warn("No files found under receipts/ on disk [{$disk}]; nothing to relink.");

            return self::SUCCESS;
        }

        $receipts = Receipt::query()->doesntHave('media')->orderBy('id')->get();

        $this->info("{$prefix}Attempting to relink {$receipts->count()} receipt(s) without a media file against ".array_sum(array_map('count', $index)).' file(s)...');

        $claimed = [];
        $matched = 0;
        $ambiguous = 0;
        $unmatched = 0;

        foreach ($receipts as $receipt) {
            if ($receipt->file_size === null || $receipt->file_type === null) {
                $unmatched++;
                $this->warn(" Receipt {$receipt->id}: no file_size/file_type to match on.");

                continue;
            }

            $key = $receipt->file_size.':'.strtolower($receipt->file_type);
            $candidates = array_values(array_filter(
                $index[$key] ?? [],
                fn (array $candidate): bool => ! in_array($candidate['path'], $claimed, true),
            ));

            $chosen = $this->chooseCandidate($receipt, $candidates);

            if ($chosen === null) {
                if ($candidates === []) {
                    $unmatched++;
                    $this->warn(" Receipt {$receipt->id}: no unclaimed file of {$receipt->file_size} bytes .{$receipt->file_type}.");
                } else {
                    $ambiguous++;
                    $this->warn(' Receipt '.$receipt->id.': '.count($candidates).' files share that size/type; needs manual review.');
                }

                continue;
            }

            $claimed[] = $chosen['path'];
            $matched++;

            if ($dryRun) {
                continue;
            }

            try {
                $receipt->addMediaFromDisk($chosen['path'], $disk)
                    ->preservingOriginal()
                    ->usingFileName(basename($chosen['path']))
                    ->usingName($receipt->original_filename ?: basename($chosen['path']))
                    ->toMediaCollection(Receipt::RECEIPT_COLLECTION);
            } catch (\Throwable $e) {
                $matched--;
                $this->error(" Receipt {$receipt->id}: attach failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("{$prefix}Done. Matched: {$matched}, ambiguous: {$ambiguous}, unmatched: {$unmatched}.");

        return self::SUCCESS;
    }

    /**
     * Index every legacy file on the disk by "size:extension".
     *
     * @return array<string, array<int, array{path: string, year: ?string, month: ?string}>>
     */
    private function indexLegacyFiles(string $disk): array
    {
        $index = [];

        foreach (Storage::disk($disk)->allFiles('receipts') as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $size = Storage::disk($disk)->size($path);
            $segments = explode('/', $path);

            $index[$size.':'.$extension][] = [
                'path' => $path,
                'year' => $segments[1] ?? null,
                'month' => $segments[2] ?? null,
            ];
        }

        return $index;
    }

    /**
     * Pick the single best candidate file for a receipt, using the created
     * month to break ties when several files share the same size and type.
     *
     * @param  array<int, array{path: string, year: ?string, month: ?string}>  $candidates
     * @return array{path: string, year: ?string, month: ?string}|null
     */
    private function chooseCandidate(Receipt $receipt, array $candidates): ?array
    {
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        if ($candidates === []) {
            return null;
        }

        $year = (string) $receipt->created_at?->year;
        $month = $receipt->created_at?->format('m');

        $sameMonth = array_values(array_filter(
            $candidates,
            fn (array $candidate): bool => $candidate['year'] === $year && $candidate['month'] === $month,
        ));

        return count($sameMonth) === 1 ? $sameMonth[0] : null;
    }
}
