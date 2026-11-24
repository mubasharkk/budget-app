<?php

namespace App\Console\Commands;

use App\Domain\Categories\Services\MisfiledCategoryRepairService;
use Illuminate\Console\Command;

class RepairMisfiledCategories extends Command
{
    protected $signature = 'categories:repair-misfiled
        {--fix : Apply the changes listed}';

    protected $description = 'List receipt lines and products filed under the wrong parent category, and repair them with --fix';

    public function handle(MisfiledCategoryRepairService $repairService): int
    {
        $lineFixes = $repairService->lineFixes();
        $productFixes = $repairService->productFixes();

        if ($lineFixes->isEmpty() && $productFixes->isEmpty()) {
            $this->info('No misfiled categories found.');

            return self::SUCCESS;
        }

        if ($lineFixes->isNotEmpty()) {
            $this->table(['Receipt lines', 'Filed as', 'Becomes'], $lineFixes->map(fn (array $fix): array => [$fix['lines'], $fix['from'], $fix['to']])->all());
        }

        if ($productFixes->isNotEmpty()) {
            $this->table(['Products', 'Filed as', 'Becomes'], $productFixes->map(fn (array $fix): array => [$fix['products'], $fix['from'], $fix['to']])->all());
        }

        if (! $this->option('fix')) {
            $this->line('Run again with --fix to apply these changes.');

            return self::SUCCESS;
        }

        $repaired = $repairService->repair();
        $this->info("Repaired {$repaired['lines']} receipt line(s) and {$repaired['products']} product(s).");

        return self::SUCCESS;
    }
}
