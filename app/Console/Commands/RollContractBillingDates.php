<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RollContractBillingDates extends Command
{
    protected $signature = 'contracts:roll-billing-dates';

    protected $description = 'Advance next_billing_date for active contracts and archive finished ones';

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $rolled = 0;

        $archived = Contract::query()
            ->where('status', ContractStatus::Active)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->update(['status' => ContractStatus::Archived]);

        Contract::query()
            ->where('status', ContractStatus::Active)
            ->whereNotNull('next_billing_date')
            ->chunkById(200, function ($contracts) use ($today, &$rolled, &$archived): void {
                foreach ($contracts as $contract) {
                    if ($contract->next_billing_date->gte($today)) {
                        continue;
                    }

                    $next = CarbonImmutable::instance($contract->next_billing_date);
                    while ($next->lt($today)) {
                        $next = $contract->billing_cycle->nextDate($next);
                    }

                    if ($contract->end_date && $next->gt(CarbonImmutable::instance($contract->end_date))) {
                        $contract->update(['status' => ContractStatus::Archived]);
                        $archived++;

                        continue;
                    }

                    $contract->update(['next_billing_date' => $next]);
                    $rolled++;
                }
            });

        $this->info("Rolled {$rolled} contract(s); archived {$archived} finished contract(s).");

        return self::SUCCESS;
    }
}
