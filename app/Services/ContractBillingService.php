<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ContractBillingService
{
    /**
     * Record a payment and advance the contract's next billing date.
     */
    public function markAsPaid(Contract $contract): Contract
    {
        if (! $contract->status->isBillable()) {
            throw ValidationException::withMessages([
                'contract' => 'Only active contracts can be marked as paid.',
            ]);
        }

        $today = CarbonImmutable::today();
        $anchor = $contract->next_billing_date
            ? CarbonImmutable::instance($contract->next_billing_date)
            : CarbonImmutable::instance($contract->start_date);

        $next = $contract->billing_cycle->nextDate($anchor);

        if ($contract->end_date && $next->gt(CarbonImmutable::instance($contract->end_date))) {
            $contract->update([
                'status' => ContractStatus::Cancelled,
                'last_paid_at' => $today,
            ]);

            return $contract->refresh();
        }

        $contract->update([
            'next_billing_date' => $next,
            'last_paid_at' => $today,
        ]);

        return $contract->refresh();
    }
}
