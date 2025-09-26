<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Display the dashboard
     */
    public function index()
    {
        return Inertia::render('Dashboard');
    }

    /**
     * Get chart data for most bought items
     */
    public function chartData(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $items = $this->dashboardService->getMostBoughtItems(
            Auth::id(),
            $startDate,
            $endDate,
            10
        );

        $chartData = $this->dashboardService->formatChartData($items);

        return response()->json([
            'data' => $chartData
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
            'stats' => $stats
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
            'data' => $spending
        ]);
    }
}