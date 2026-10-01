<?php

namespace App\Domain\Contracts\Services;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
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
                'status' => ContractStatus::Archived,
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

    /**
     * Active contracts billing within the next N days (today included), soonest first,
     * with the total for the window and for the coming week.
     *
     * @return array{days: int, total: float, count: int, due_this_week: array{total: float, count: int}, bills: array<int, array{contract_id: int, name: string, provider: ?string, amount: float, billing_cycle: string, due_date: string, days_until_due: int}>}
     */
    public function upcomingBills(int $userId, int $days = 30, ?CarbonInterface $anchor = null): array
    {
        $today = CarbonImmutable::instance($anchor ?? CarbonImmutable::today())->startOfDay();
        $until = $today->addDays($days);

        $bills = Contract::query()
            ->with('provider:id,name')
            ->where('user_id', $userId)
            ->where('status', ContractStatus::Active)
            ->whereDate('next_billing_date', '>=', $today)
            ->whereDate('next_billing_date', '<=', $until)
            ->orderBy('next_billing_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Contract $contract): array => [
                'contract_id' => $contract->id,
                'name' => $contract->name,
                'provider' => $contract->provider?->name,
                'amount' => (float) $contract->amount,
                'billing_cycle' => $contract->billing_cycle->value,
                'due_date' => $contract->next_billing_date->toDateString(),
                'days_until_due' => (int) $today->diffInDays(
                    CarbonImmutable::instance($contract->next_billing_date)->startOfDay(),
                ),
            ]);

        $thisWeek = $bills->where('days_until_due', '<=', 7);

        return [
            'days' => $days,
            'total' => round((float) $bills->sum('amount'), 2),
            'count' => $bills->count(),
            'due_this_week' => [
                'total' => round((float) $thisWeek->sum('amount'), 2),
                'count' => $thisWeek->count(),
            ],
            'bills' => $bills->values()->all(),
        ];
    }

    /**
     * Active contracts with next_billing_date in the calendar month that are not yet paid.
     *
     * @return array{month: string, total: float, count: int, paid_count: int}
     */
    public function dueThisMonthSummary(int $userId, ?CarbonInterface $anchor = null): array
    {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());
        $monthStart = $anchor->startOfMonth();
        $monthEnd = $anchor->endOfMonth();

        $dueThisMonth = Contract::query()
            ->where('user_id', $userId)
            ->where('status', ContractStatus::Active)
            ->whereNotNull('next_billing_date')
            ->whereBetween('next_billing_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get();

        $unpaid = $dueThisMonth->filter(
            fn (Contract $contract): bool => $contract->isUnpaidForCurrentDue()
        );

        return [
            'month' => $monthStart->format('Y-m'),
            'total' => round($unpaid->sum(fn (Contract $contract): float => (float) $contract->amount), 2),
            'count' => $unpaid->count(),
            'paid_count' => $dueThisMonth->count() - $unpaid->count(),
        ];
    }

    /**
     * @return Collection<int, Contract>
     */
    public function payableThisMonth(int $userId, ?CarbonInterface $anchor = null): Collection
    {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());

        return Contract::query()
            ->where('user_id', $userId)
            ->where('status', ContractStatus::Active)
            ->whereNotNull('next_billing_date')
            ->get()
            ->filter(fn (Contract $contract): bool => $contract->isPayableThisMonth($anchor));
    }
}
