<?php

namespace App\Services;

use App\Enums\BudgetPeriod;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Digest;
use App\Models\Saving;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DashboardSnapshotService
{
    public function __construct(
        private ExpenseService $expenseService,
        private BudgetService $budgetService,
        private PriceIntelligenceService $priceIntelligenceService,
        private ConsumptionService $consumptionService,
        private RenewalReminderService $renewalReminderService,
        private AnomalyDetectionService $anomalyDetectionService,
        private RecommendationService $recommendationService,
        private IncomeService $incomeService,
    ) {}

    /**
     * Compact cross-feature snapshot for the dashboard at-a-glance view.
     *
     * @return array<string, mixed>
     */
    public function snapshot(int $userId, string $period = 'month'): array
    {
        $budgetPeriod = $period === 'week' ? BudgetPeriod::Weekly : BudgetPeriod::Monthly;
        $anchor = CarbonImmutable::today();
        [$start, $end] = $this->periodRange($budgetPeriod, $anchor);

        $overview = $this->expenseService->overview($userId, $start, $end, $period);
        $budget = $this->budgetService->summary($userId, $budgetPeriod, $anchor);
        $savings = $this->priceIntelligenceService->savingsOpportunities(
            $userId,
            $start->toDateString(),
            $end->toDateString(),
            5,
        );
        $topItem = $this->consumptionService->topItems($userId, 'spend', $start->toDateString(), $end->toDateString(), null, 1)->first();
        $renewals = $this->renewalReminderService->upcoming($userId, 14);
        $anomalies = $this->anomalyDetectionService->detect($userId, $start, $end);
        $recommendations = $this->recommendationService->recommendations($userId, $start->toDateString(), $end->toDateString(), 3);

        $monthlyFixed = round(Contract::query()
            ->where('user_id', $userId)
            ->where('status', ContractStatus::Active)
            ->get()
            ->sum(fn (Contract $c): float => $c->projectedMonthlyAmount()), 2);

        $latestDigest = Digest::query()
            ->where('user_id', $userId)
            ->orderByDesc('period_end')
            ->first(['id', 'summary', 'period_start', 'period_end']);

        $prevStart = $period === 'week'
            ? $start->copy()->subWeek()
            : $start->copy()->subMonth()->startOfMonth();
        $prevEnd = $period === 'week'
            ? $start->copy()->subDay()
            : $start->copy()->subMonth()->endOfMonth();
        $previousVariable = round($this->expenseService->variableTotal($userId, $prevStart, $prevEnd), 2);
        $previousTotal = round($overview['fixed'] + $previousVariable, 2);
        $delta = round($overview['total'] - $previousTotal, 2);

        $potentialSavings = round($savings->sum(fn ($row) => (float) $row->potential_savings), 2);

        $user = \App\Models\User::find($userId);
        $periodIncome = $user ? $this->incomeService->periodIncome($user, $period, $anchor) : 0.0;
        $balance = round($periodIncome - $overview['variable'] - $overview['fixed'], 2);
        $saved = round((float) Saving::query()
            ->where('user_id', $userId)
            ->whereBetween('saved_on', [$start->toDateString(), $end->toDateString()])
            ->sum('amount'), 2);

        return [
            'period' => $period,
            'expenses' => [
                'total' => $overview['total'],
                'fixed' => $overview['fixed'],
                'variable' => $overview['variable'],
                'delta' => $delta,
                'top_category' => $overview['by_category'][0]['category'] ?? null,
                'top_category_amount' => $overview['by_category'][0]['total'] ?? 0,
                'href' => route('insights'),
            ],
            'budgets' => [
                'budgeted' => $budget['budgeted'],
                'actual' => $budget['actual'],
                'over_count' => $budget['over_count'],
                'warning_count' => $budget['warning_count'],
                'status' => $budget['over_count'] > 0 ? 'over' : ($budget['warning_count'] > 0 ? 'warning' : 'on_track'),
                'href' => route('budgets.index', ['period' => $budgetPeriod->value]),
            ],
            'income' => $budget['income'],
            'balance' => [
                'income' => $periodIncome,
                'expenses' => $overview['variable'],
                'contracts' => $overview['fixed'],
                'balance' => $balance,
                'saved' => $saved,
                'href' => route('savings.index'),
            ],
            'savings' => [
                'opportunity_count' => $savings->count(),
                'potential_total' => $potentialSavings,
                'href' => route('deals'),
            ],
            'consumption' => [
                'top_item' => $topItem?->item_name,
                'top_spend' => $topItem ? (float) $topItem->total_spend : 0,
                'href' => route('insights'),
            ],
            'contracts' => [
                'monthly_fixed' => $monthlyFixed,
                'due_soon' => $renewals->count(),
                'href' => route('contracts.index'),
            ],
            'agent' => [
                'digest_summary' => $latestDigest?->summary,
                'digest_period' => $latestDigest
                    ? $latestDigest->period_start->format('M Y')
                    : null,
                'renewal_count' => $renewals->count(),
                'anomaly_count' => count($anomalies),
                'recommendation_count' => count($recommendations),
                'href' => route('agent'),
            ],
        ];
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

        return [$anchor->startOfMonth(), $anchor->endOfMonth()];
    }
}
