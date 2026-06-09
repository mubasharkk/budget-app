<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RenewalReminderService
{
    /**
     * Upcoming contract billing dates and renewals.
     *
     * @return Collection<int, object>
     */
    public function upcoming(int $userId, int $daysAhead = 14): Collection
    {
        $today = CarbonImmutable::today();
        $until = $today->copy()->addDays($daysAhead);

        return Contract::query()
            ->with(['provider:id,name', 'category:id,name'])
            ->where('user_id', $userId)
            ->where('status', ContractStatus::Active)
            ->where(function ($query) use ($today, $until): void {
                $query->whereBetween('next_billing_date', [$today, $until])
                    ->orWhereBetween('end_date', [$today, $until]);
            })
            ->orderBy('next_billing_date')
            ->get()
            ->map(function (Contract $contract) use ($today): object {
                $daysUntilBilling = $contract->next_billing_date
                    ? $today->diffInDays(CarbonImmutable::instance($contract->next_billing_date), false)
                    : null;

                return (object) [
                    'contract_id' => $contract->id,
                    'name' => $contract->name,
                    'provider' => $contract->provider?->name,
                    'category' => $contract->category?->name,
                    'amount' => (float) $contract->amount,
                    'currency' => $contract->currency,
                    'billing_cycle' => $contract->billing_cycle->value,
                    'next_billing_date' => $contract->next_billing_date?->toDateString(),
                    'end_date' => $contract->end_date?->toDateString(),
                    'days_until_billing' => $daysUntilBilling,
                    'is_renewal' => $contract->end_date !== null,
                ];
            });
    }
}
