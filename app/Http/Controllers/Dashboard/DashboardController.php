<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\BudgetPeriod;
use App\Http\Controllers\Controller;
use App\Services\BudgetService;
use App\Services\ConsumptionService;
use App\Services\Dashboard\DashboardService;
use App\Services\DashboardSnapshotService;
use App\Services\ExpenseService;
use App\Services\PriceIntelligenceService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected ExpenseService $expenseService,
        protected ConsumptionService $consumptionService,
        protected PriceIntelligenceService $priceIntelligenceService,
        protected BudgetService $budgetService,
        protected DashboardSnapshotService $dashboardSnapshotService,
    ) {}

    /**
     * Compact at-a-glance snapshot across all expense features.
     */
    public function snapshot(Request $request)
    {
        $period = $request->get('period') === 'week' ? 'week' : 'month';

        return response()->json(
            $this->dashboardSnapshotService->snapshot(Auth::id(), $period)
        );
    }

    /**
     * Display the dashboard
     */
    public function index()
    {
        return Inertia::render('Dashboard');
    }

    /**
     * Display the consumption insights page.
     */
    public function insights()
    {
        return Inertia::render('Insights');
    }

    /**
     * Display the savings / price intelligence page.
     */
    public function savings()
    {
        return Inertia::render('Savings');
    }

    /**
     * Budget progress for the current month or week.
     */
    public function budgets(Request $request)
    {
        $period = $request->get('period') === 'weekly'
            ? BudgetPeriod::Weekly
            : BudgetPeriod::Monthly;

        return response()->json(
            $this->budgetService->summary(Auth::id(), $period)
        );
    }

    /**
     * Price intelligence: savings opportunities, cheapest vendors, and price trends.
     */
    public function savingsData(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $userId = Auth::id();

        return response()->json([
            'savings_opportunities' => $this->priceIntelligenceService->savingsOpportunities(
                $userId, $startDate, $endDate
            ),
            'cheapest_vendors' => $this->priceIntelligenceService->cheapestVendors($userId),
            'price_trends' => $this->priceIntelligenceService->priceTrends($userId),
        ]);
    }

    /**
     * Consumption analytics: most-consumed items (by quantity and spend)
     * and a vendor spend leaderboard, filterable by date range and category.
     */
    public function consumption(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $categoryId = $request->get('category_id') ?: null;

        return response()->json([
            'top_by_quantity' => $this->consumptionService->topItems(
                Auth::id(), 'quantity', $startDate, $endDate, $categoryId
            ),
            'top_by_spend' => $this->consumptionService->topItems(
                Auth::id(), 'spend', $startDate, $endDate, $categoryId
            ),
            'vendors' => $this->consumptionService->vendorLeaderboard(
                Auth::id(), $startDate, $endDate
            ),
        ]);
    }

    /**
     * Get chart data for most bought items
     */
    public function chartData(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $categoryId = $request->get('category_id');

        $items = $this->dashboardService->getMostBoughtItems(
            Auth::id(),
            $startDate,
            $endDate,
            10,
            $categoryId
        );

        $chartData = $this->dashboardService->formatChartData($items);

        return response()->json($chartData);
    }

    /**
     * Get categories for filter dropdown
     */
    public function categories(Request $request)
    {
        $categories = $this->dashboardService->getCategoriesForFilter();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function stats(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $stats = $this->dashboardService->getDashboardStats(
            Auth::id(),
            $startDate,
            $endDate
        );

        return response()->json([
            'stats' => $stats,
        ]);
    }

    /**
     * Get spending by category
     */
    public function spendingByCategory(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $spending = $this->dashboardService->getSpendingByCategory(
            Auth::id(),
            $startDate,
            $endDate
        );

        return response()->json([
            'data' => $spending,
        ]);
    }

    /**
     * Unified fixed + variable overview for the current month or week,
     * including the delta versus the previous period.
     */
    public function overview(Request $request)
    {
        $period = $this->resolvePeriod($request);
        [$start, $end] = $this->currentPeriodRange($period);
        [$prevStart, $prevEnd] = $this->previousPeriodRange($period);

        $current = $this->expenseService->overview(Auth::id(), $start, $end, $period);

        $previousVariable = round($this->expenseService->variableTotal(Auth::id(), $prevStart, $prevEnd), 2);
        $previousTotal = round($current['fixed'] + $previousVariable, 2);

        $delta = round($current['total'] - $previousTotal, 2);
        $deltaPercent = $previousTotal > 0 ? round(($delta / $previousTotal) * 100, 1) : null;

        return response()->json([
            'period' => $period,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'current' => $current,
            'previous_total' => $previousTotal,
            'delta' => $delta,
            'delta_percent' => $deltaPercent,
        ]);
    }

    /**
     * Spending trend over the most recent periods.
     */
    public function trend(Request $request)
    {
        $period = $this->resolvePeriod($request);
        $points = $period === 'week' ? 8 : 6;

        return response()->json([
            'period' => $period,
            'trend' => $this->expenseService->trend(Auth::id(), CarbonImmutable::today(), $period, $points),
        ]);
    }

    private function resolvePeriod(Request $request): string
    {
        return $request->get('period') === 'week' ? 'week' : 'month';
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function currentPeriodRange(string $period): array
    {
        $today = CarbonImmutable::today();

        return $period === 'week'
            ? [$today->startOfWeek(CarbonInterface::MONDAY), $today->endOfWeek(CarbonInterface::SUNDAY)]
            : [$today->startOfMonth(), $today->endOfMonth()];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previousPeriodRange(string $period): array
    {
        $today = CarbonImmutable::today();

        if ($period === 'week') {
            $start = $today->startOfWeek(CarbonInterface::MONDAY)->subWeek();

            return [$start, $start->endOfWeek(CarbonInterface::SUNDAY)];
        }

        $start = $today->startOfMonth()->subMonth();

        return [$start, $start->endOfMonth()];
    }
}
