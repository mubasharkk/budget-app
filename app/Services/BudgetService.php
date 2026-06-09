<?php

namespace App\Services;

use App\Enums\BudgetPeriod;
use App\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BudgetService
{
    public function __construct(
        private ExpenseService $expenseService,
        private IncomeService $incomeService,
    ) {}

    /**
     * Progress for every active budget in the given period.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allProgress(int $userId, BudgetPeriod $period, ?CarbonInterface $anchor = null): array
    {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());
        [$start, $end] = $this->periodRange($period, $anchor);

        $budgets = Budget::query()
            ->with('category:id,name')
            ->where('user_id', $userId)
            ->where('period', $period)
            ->where('starts_on', '<=', $end->toDateString())
            ->orderBy('category_id')
            ->get();

        $overview = $this->expenseService->overview(
            $userId,
            $start,
            $end,
            $period->toExpensePeriod(),
        );

        $byCategory = collect($overview['by_category'])->keyBy('category');

        return $budgets->map(fn (Budget $budget): array => $this->buildProgressRow(
            $budget,
            $overview,
            $byCategory,
            $start,
            $end,
            $anchor,
        ))->all();
    }

    /**
     * Dashboard summary across budgets for a period.
     *
     * @return array{period: string, budgeted: float, actual: float, remaining: float, over_count: int, warning_count: int, on_track_count: int, items: array<int, array<string, mixed>>, income: ?array<string, mixed>}
     */
    public function summary(int $userId, BudgetPeriod $period, ?CarbonInterface $anchor = null): array
    {
        $items = $this->allProgress($userId, $period, $anchor);

        $budgeted = round(array_sum(array_column($items, 'budget_amount')), 2);
        $actual = round(array_sum(array_column($items, 'actual')), 2);

        $overCount = count(array_filter($items, fn (array $row): bool => $row['status'] === 'over'));
        $warningCount = count(array_filter($items, fn (array $row): bool => $row['status'] === 'warning'));

        $user = User::query()->find($userId);
        $income = $user
            ? $this->incomeService->context($user, $actual, $budgeted, $period->toExpensePeriod())
            : null;

        return [
            'period' => $period->value,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'remaining' => round(max(0, $budgeted - $actual), 2),
            'over_count' => $overCount,
            'warning_count' => $warningCount,
            'on_track_count' => count($items) - $overCount - $warningCount,
            'items' => $items,
            'income' => $income,
        ];
    }

    /**
     * @param  Collection<string, array{category: string, fixed: float, variable: float, total: float}>  $byCategory
     * @return array<string, mixed>
     */
    private function buildProgressRow(
        Budget $budget,
        array $overview,
        Collection $byCategory,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $anchor,
    ): array {
        $budgetAmount = (float) $budget->amount;

        if ($budget->category_id) {
            $categoryName = $budget->category?->name ?? 'Uncategorized';
            $categoryRow = $byCategory->get($categoryName);
            $actual = round($categoryRow['total'] ?? 0, 2);
            $fixed = round($categoryRow['fixed'] ?? 0, 2);
            $variable = round($categoryRow['variable'] ?? 0, 2);
        } else {
            $actual = round($overview['total'], 2);
            $fixed = round($overview['fixed'], 2);
            $variable = round($overview['variable'], 2);
            $categoryName = null;
        }

        $percentUsed = $budgetAmount > 0 ? round(($actual / $budgetAmount) * 100, 1) : 0;
        $projected = $this->projectedSpend($fixed, $variable, $start, $end, $anchor);
        $projectedPercent = $budgetAmount > 0 ? round(($projected / $budgetAmount) * 100, 1) : 0;

        return [
            'budget_id' => $budget->id,
            'category_id' => $budget->category_id,
            'category_name' => $categoryName,
            'label' => $categoryName ?? 'All categories',
            'period' => $budget->period->value,
            'currency' => $budget->currency,
            'budget_amount' => $budgetAmount,
            'actual' => $actual,
            'fixed' => $fixed,
            'variable' => $variable,
            'remaining' => round(max(0, $budgetAmount - $actual), 2),
            'percent_used' => $percentUsed,
            'projected' => $projected,
            'projected_percent' => $projectedPercent,
            'status' => $this->resolveStatus($percentUsed),
            'projected_status' => $this->resolveStatus($projectedPercent),
            'is_over' => $actual > $budgetAmount,
            'is_warning' => $actual >= $budgetAmount * 0.8 && $actual <= $budgetAmount,
        ];
    }

    private function projectedSpend(
        float $fixed,
        float $variable,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $anchor,
    ): float {
        $daysInPeriod = max(1, $start->diffInDays($end) + 1);
        $effectiveAnchor = $anchor->greaterThan($end) ? $end : $anchor;
        $daysElapsed = max(1, $start->diffInDays($effectiveAnchor) + 1);

        $variableProjected = ($variable / $daysElapsed) * $daysInPeriod;

        return round($fixed + $variableProjected, 2);
    }

    private function resolveStatus(float $percentUsed): string
    {
        if ($percentUsed >= 100) {
            return 'over';
        }

        if ($percentUsed >= 80) {
            return 'warning';
        }

        return 'on_track';
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodRange(BudgetPeriod $period, CarbonImmutable $anchor): array
    {
        if ($period === BudgetPeriod::Weekly) {
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
