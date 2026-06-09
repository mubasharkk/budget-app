<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyDigest;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateMonthlyDigests extends Command
{
    protected $signature = 'digests:generate-monthly {--month= : YYYY-MM of the period to summarize}';

    protected $description = 'Queue monthly budget digests for all users';

    public function handle(): int
    {
        $month = $this->option('month');
        $count = 0;

        User::query()->chunkById(100, function ($users) use ($month, &$count): void {
            foreach ($users as $user) {
                GenerateMonthlyDigest::dispatch($user, $month);
                $count++;
            }
        });

        $this->info("Queued {$count} monthly digest job(s).");

        return self::SUCCESS;
    }
}
