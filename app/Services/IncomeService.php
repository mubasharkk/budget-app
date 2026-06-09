<?php

namespace App\Services;

use App\Enums\IncomeType;
use App\Models\User;

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
    ): ?array {
        if ($user->monthly_income === null || (float) $user->monthly_income <= 0) {
            return null;
        }

        $monthlyIncome = (float) $user->monthly_income;
        $periodIncome = $period === 'week'
            ? round($monthlyIncome / 4.33, 2)
            : $monthlyIncome;

        $incomeType = $user->income_type ?? IncomeType::Net;

        return [
            'monthly_income' => $monthlyIncome,
            'period_income' => $periodIncome,
            'income_type' => $incomeType->value,
            'income_type_label' => $incomeType->shortLabel(),
            'currency' => $user->income_currency ?? 'EUR',
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
        ];
    }
}
