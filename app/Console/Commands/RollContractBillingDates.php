<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RollContractBillingDates extends Command
{
    protected $signature = 'contracts:roll-billing-dates';

    protected $description = 'Advance next_billing_date for active contracts and cancel expired ones';

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $rolled = 0;
        $cancelled = 0;

        Contract::query()
            ->where('status', ContractStatus::Active)
            ->whereNotNull('next_billing_date')
            ->chunkById(200, function ($contracts) use ($today, &$rolled, &$cancelled): void {
                foreach ($contracts as $contract) {
                    if ($contract->end_date && $contract->end_date->lt($today)) {
                        $contract->update(['status' => ContractStatus::Cancelled]);
                        $cancelled++;

                        continue;
                    }

                    if ($contract->next_billing_date->gte($today)) {
                        continue;
                    }

                    $next = CarbonImmutable::instance($contract->next_billing_date);
                    while ($next->lt($today)) {
                        $next = $contract->billing_cycle->nextDate($next);
                    }

                    if ($contract->end_date && $next->gt(CarbonImmutable::instance($contract->end_date))) {
                        $contract->update(['status' => ContractStatus::Cancelled]);
                        $cancelled++;

                        continue;
                    }

                    $contract->update(['next_billing_date' => $next]);
                    $rolled++;
                }
            });

        $this->info("Rolled {$rolled} contract(s); cancelled {$cancelled} expired contract(s).");

        return self::SUCCESS;
    }
}
