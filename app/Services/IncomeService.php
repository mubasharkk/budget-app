<?php

namespace App\Services;

use App\Enums\IncomeType;
use App\Models\Income;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class IncomeService
{
    /**
     * Income context for budget and spending comparisons.
     *
     * @return array<string, mixed>|null
     */
    public function context(
        User $user,
        float $actualSpend,
        float $budgetedTotal,
        string $period = 'month',
        ?CarbonInterface $anchor = null,
    ): ?array {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());
        [$start, $end] = $this->periodRange($period, $anchor);

        $recurringPeriodIncome = $this->recurringForPeriod($user, $period);
        $oneTimePeriodIncome = $this->oneTimeForPeriod($user, $start, $end);
        $periodIncome = round($recurringPeriodIncome + $oneTimePeriodIncome, 2);

        if ($periodIncome <= 0) {
            return null;
        }

        $monthlyIncome = (float) ($user->monthly_income ?? 0);
        $incomeType = $user->income_type ?? IncomeType::Net;
        $currency = $user->income_currency ?? $this->defaultCurrency($user);

        return [
            'monthly_income' => $monthlyIncome > 0 ? $monthlyIncome : null,
            'recurring_period_income' => $recurringPeriodIncome,
            'one_time_period_income' => $oneTimePeriodIncome,
            'period_income' => $periodIncome,
            'income_type' => $incomeType->value,
            'income_type_label' => $incomeType->shortLabel(),
            'currency' => $currency,
            'spend_percent' => $periodIncome > 0
                ? round(($actualSpend / $periodIncome) * 100, 1)
                : null,
            'budgeted_percent' => $periodIncome > 0
                ? round(($budgetedTotal / $periodIncome) * 100, 1)
                : null,
            'disposable' => round($periodIncome - $actualSpend, 2),
            'remaining_after_budgets' => round($periodIncome - $budgetedTotal, 2),
            'is_over_income' => $actualSpend > $periodIncome,
            'budgets_exceed_income' => $budgetedTotal > $periodIncome,
            'has_recurring_income' => $monthlyIncome > 0,
            'has_one_time_income' => $oneTimePeriodIncome > 0,
        ];
    }

    /**
     * Raw period income (recurring + one-time) regardless of whether it is zero.
     */
    public function periodIncome(User $user, string $period, ?CarbonInterface $anchor = null): float
    {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());
        [$start, $end] = $this->periodRange($period, $anchor);

        return round(
            $this->recurringForPeriod($user, $period) + $this->oneTimeForPeriod($user, $start, $end),
            2,
        );
    }

    public function recurringForPeriod(User $user, string $period): float
    {
        if ($user->monthly_income === null || (float) $user->monthly_income <= 0) {
            return 0.0;
        }

        $monthlyIncome = (float) $user->monthly_income;

        return $period === 'week'
            ? round($monthlyIncome / 4.33, 2)
            : $monthlyIncome;
    }

    public function oneTimeForPeriod(User $user, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return round((float) Income::query()
            ->where('user_id', $user->id)
            ->whereBetween('received_on', [$start->toDateString(), $end->toDateString()])
            ->sum('amount'), 2);
    }

    private function defaultCurrency(User $user): string
    {
        return Income::query()
            ->where('user_id', $user->id)
            ->orderByDesc('received_on')
            ->value('currency') ?? 'EUR';
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodRange(string $period, CarbonImmutable $anchor): array
    {
        if ($period === 'week') {
            return [
                $anchor->startOfWeek(CarbonInterface::MONDAY),
                $anchor->endOfWeek(CarbonInterface::SUNDAY),
            ];
        }

        return [
            $anchor->startOfMonth(),
            $anchor->endOfMonth(),
        ];
    }
}
