<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Analytics\Data\ConsumptionReportFilters;
use App\Domain\Analytics\Services\ConsumptionService;
use App\Domain\Analytics\Services\DashboardService;
use App\Domain\Analytics\Services\DashboardSnapshotService;
use App\Domain\Analytics\Services\ExpenseOverviewService;
use App\Domain\Budgets\Services\BudgetService;
use App\Domain\Identity\Services\UserSettingsService;
use App\Domain\Products\Services\PriceIntelligenceService;
use App\Enums\BudgetPeriod;
use App\Enums\DashboardSection;
use App\Enums\ExpenseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private ExpenseOverviewService $expenseOverviewService,
        private ConsumptionService $consumptionService,
        private PriceIntelligenceService $priceIntelligenceService,
        private BudgetService $budgetService,
        private DashboardSnapshotService $dashboardSnapshotService,
        private UserSettingsService $userSettingsService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'sections' => $this->userSettingsService->dashboardSections($request->user()),
            'availableSections' => DashboardSection::options(),
        ]);
    }

    /**
     * Save which optional sections the user wants on their dashboard.
     */
    public function updateSettings(DashboardSettingsRequest $request): RedirectResponse
    {
        $this->userSettingsService->updateDashboardSections(
            $request->user(),
            $request->validated('sections'),
        );

        return redirect()->route('dashboard');
    }

    public function insights(): Response
    {
        return Inertia::render('Insights');
    }

    public function deals(): Response
    {
        return Inertia::render('Deals');
    }

    /**
     * Compact at-a-glance snapshot across all expense features.
     */
    public function snapshot(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboardSnapshotService->snapshot($request->user()->id, $this->period($request))
        );
    }

    /**
     * Budget progress for the current month or week.
     */
    public function budgets(Request $request): JsonResponse
    {
        $period = BudgetPeriod::tryFrom((string) $request->query('period')) ?? BudgetPeriod::Monthly;

        return response()->json($this->budgetService->summary($request->user()->id, $period));
    }

    /**
     * Price intelligence: savings opportunities, cheapest vendors, and price trends.
     */
    public function dealsData(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'savings_opportunities' => $this->priceIntelligenceService->savingsOpportunities(
                $userId, $request->query('start_date'), $request->query('end_date')
            ),
            'cheapest_vendors' => $this->priceIntelligenceService->cheapestVendors($userId),
            'price_trends' => $this->priceIntelligenceService->priceTrends($userId),
        ]);
    }

    /**
     * Consumption analytics, filterable by period/date range, category and metric.
     */
    public function consumption(Request $request): JsonResponse
    {
        return response()->json($this->consumptionService->report(
            $request->user()->id,
            ConsumptionReportFilters::fromArray($request->query()),
        ));
    }

    /**
     * Chart data for the most bought items.
     */
    public function chartData(Request $request): JsonResponse
    {
        return response()->json($this->dashboardService->mostBoughtItemsChart(
            $request->user()->id,
            $request->query('start_date'),
            $request->query('end_date'),
            $request->filled('category_id') ? $request->integer('category_id') : null,
        ));
    }

    public function categories(): JsonResponse
    {
        return response()->json(['categories' => $this->dashboardService->getCategoriesForFilter()]);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json(['stats' => $this->dashboardService->getDashboardStats(
            $request->user()->id,
            $request->query('start_date'),
            $request->query('end_date'),
        )]);
    }

    public function spendingByCategory(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboardService->getSpendingByCategory(
            $request->user()->id,
            $request->query('start_date'),
            $request->query('end_date'),
        )]);
    }

    /**
     * Unified fixed + variable overview for the current period, with the delta versus the previous one.
     */
    public function overview(Request $request): JsonResponse
    {
        return response()->json(
            $this->expenseOverviewService->summary($request->user(), $this->period($request), $this->scope($request))
        );
    }

    /**
     * Spending trend over the most recent periods.
     */
    public function trend(Request $request): JsonResponse
    {
        return response()->json(
            $this->expenseOverviewService->trend($request->user()->id, $this->period($request), $this->scope($request))
        );
    }

    private function period(Request $request): string
    {
        return $request->query('period') === 'week' ? 'week' : 'month';
    }

    private function scope(Request $request): ?ExpenseType
    {
        return ExpenseType::tryFrom((string) $request->query('scope'));
    }
}
