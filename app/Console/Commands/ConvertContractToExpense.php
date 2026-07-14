<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertContractToExpense extends Command
{
    protected $signature = 'contracts:convert-to-expense
        {contract : ID of the contract to convert}
        {--date= : Date (Y-m-d) for the one-time expense (default: next billing date, else start date, else today)}
        {--keep : Archive the contract instead of deleting it}
        {--force : Skip the confirmation prompt}
        {--dry-run : Show what would happen without making any changes}';

    protected $description = 'Convert a recurring contract into a one-time expense';

    public function handle(): int
    {
        $contract = Contract::with(['provider', 'category'])->find($this->argument('contract'));

        if ($contract === null) {
            $this->error("Contract #{$this->argument('contract')} not found.");

            return self::FAILURE;
        }

        $spentOn = $this->resolveSpentOn($contract);
        $keep = (bool) $this->option('keep');
        $dryRun = (bool) $this->option('dry-run');
        $action = $keep ? 'archive' : 'delete';

        $this->line("Contract #{$contract->id}: {$contract->name} — {$contract->amount} {$contract->currency} ({$contract->billing_cycle->value})");
        $this->line("Will create a one-time {$contract->expense_type->value} expense of {$contract->amount} {$contract->currency} dated {$spentOn->toDateString()}, then {$action} the contract.");

        if ($dryRun) {
            $this->info('[DRY RUN] No changes made.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Convert contract #{$contract->id} to a one-time expense and {$action} it?", true)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $expense = DB::transaction(function () use ($contract, $spentOn, $keep): Expense {
            $expense = Expense::create([
                'user_id' => $contract->user_id,
                'amount' => $contract->amount,
                'currency' => $contract->currency,
                'spent_on' => $spentOn->toDateString(),
                'description' => $contract->name,
                'expense_type' => $contract->expense_type,
                'notes' => $this->buildNotes($contract),
            ]);

            if ($keep) {
                $contract->update(['status' => ContractStatus::Archived]);
            } else {
                $contract->delete();
            }

            return $expense;
        });

        $this->info("Created expense #{$expense->id} and {$action}d contract #{$contract->id}.");

        return self::SUCCESS;
    }

    private function resolveSpentOn(Contract $contract): CarbonImmutable
    {
        if ($date = $this->option('date')) {
            return CarbonImmutable::parse($date);
        }

        return CarbonImmutable::instance(
            $contract->next_billing_date
                ?? $contract->start_date
                ?? CarbonImmutable::today()
        );
    }

    private function buildNotes(Contract $contract): string
    {
        $parts = ["Converted from contract #{$contract->id} \"{$contract->name}\"."];

        if ($contract->provider !== null) {
            $parts[] = "Provider: {$contract->provider->name}.";
        }

        if ($contract->category !== null) {
            $parts[] = "Category: {$contract->category->name}.";
        }

        if (! empty($contract->notes)) {
            $parts[] = $contract->notes;
        }

        return implode(' ', $parts);
    }
}
