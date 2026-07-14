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
                array_pop($claimed);
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
     * @return array<string, array<int, array{path: string, mtime: int}>>
     */
    private function indexLegacyFiles(string $disk): array
    {
        $index = [];

        foreach (Storage::disk($disk)->allFiles('receipts') as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $size = Storage::disk($disk)->size($path);

            $index[$size.':'.$extension][] = [
                'path' => $path,
                'mtime' => Storage::disk($disk)->lastModified($path),
            ];
        }

        return $index;
    }

    /**
     * Pick the single best candidate file for a receipt. When several files
     * share the same size and type, choose the one whose on-disk modified time
     * is closest to the receipt's upload time (created_at) — each file was
     * written when its receipt was created, so this is a unique tiebreak.
     *
     * @param  array<int, array{path: string, mtime: int}>  $candidates
     * @return array{path: string, mtime: int}|null
     */
    private function chooseCandidate(Receipt $receipt, array $candidates): ?array
    {
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        if ($candidates === [] || $receipt->created_at === null) {
            return null;
        }

        $createdAt = $receipt->created_at->getTimestamp();

        usort(
            $candidates,
            fn (array $a, array $b): int => abs($a['mtime'] - $createdAt) <=> abs($b['mtime'] - $createdAt),
        );

        // Require a strictly-closest file; an exact tie in proximity is unsafe to guess.
        if (abs($candidates[0]['mtime'] - $createdAt) === abs($candidates[1]['mtime'] - $createdAt)) {
            return null;
        }

        return $candidates[0];
    }
}
