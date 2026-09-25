<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Incomes\Services\IncomeService;
use App\Domain\Shared\Support\PeriodRange;
use App\Enums\ExpenseType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ExpenseOverviewService
{
    public function __construct(
        private ExpenseService $expenseService,
        private IncomeService $incomeService,
    ) {}

    /**
     * Fixed + variable spend for the current week/month, the delta versus the previous
     * period, and the income-minus-spend balance.
     *
     * @return array<string, mixed>
     */
    public function summary(User $user, string $period, ?ExpenseType $scope = null, ?CarbonInterface $anchor = null): array
    {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());
        [$start, $end] = PeriodRange::current($period, $anchor);
        [$previousStart, $previousEnd] = PeriodRange::previous($period, $anchor);

        $current = $this->expenseService->overview($user->id, $start, $end, $period, $scope);

        // Fixed costs are normalised per period, so the previous period reuses the current fixed figure.
        $previousVariable = round($this->expenseService->variableTotal($user->id, $previousStart, $previousEnd, $scope), 2);
        $previousTotal = round($current['fixed'] + $previousVariable, 2);

        $delta = round($current['total'] - $previousTotal, 2);
        $income = $this->incomeService->periodIncome($user, $period, $anchor);

        return [
            'period' => $period,
            'scope' => $scope?->value,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'current' => $current,
            'previous_total' => $previousTotal,
            'delta' => $delta,
            'delta_percent' => $previousTotal > 0 ? round(($delta / $previousTotal) * 100, 1) : null,
            'balance' => [
                'income' => $income,
                'expenses' => $current['variable'],
                'contracts' => $current['fixed'],
                'balance' => round($income - $current['variable'] - $current['fixed'], 2),
            ],
        ];
    }

    /**
     * Spend over the most recent 8 weeks or 6 months.
     *
     * @return array{period: string, scope: ?string, trend: array<int, array<string, mixed>>}
     */
    public function trend(int $userId, string $period, ?ExpenseType $scope = null): array
    {
        $points = $period === 'week' ? 8 : 6;

        return [
            'period' => $period,
            'scope' => $scope?->value,
            'trend' => $this->expenseService->trend($userId, CarbonImmutable::today(), $period, $points, $scope),
        ];
    }
}
