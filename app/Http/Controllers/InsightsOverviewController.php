<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Services\InsightsOverviewService;
use App\Http\Requests\InsightsOverviewRequest;
use Illuminate\Http\JsonResponse;

/**
 * The figures behind the Insights page.
 */
class InsightsOverviewController extends Controller
{
    public function __construct(private InsightsOverviewService $overviewService) {}

    public function show(InsightsOverviewRequest $request): JsonResponse
    {
        return response()->json($this->overviewService->overview(
            $request->user(),
            $request->window(),
            $request->categoryId(),
        ));
    }
}
