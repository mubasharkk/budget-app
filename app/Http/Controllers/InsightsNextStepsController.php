<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Services\InsightsNextStepsService;
use App\Http\Requests\InsightsNextStepsRequest;
use Illuminate\Http\JsonResponse;

/**
 * Savings, what is due soon and what to fix, on the Insights page.
 */
class InsightsNextStepsController extends Controller
{
    public function __construct(private InsightsNextStepsService $nextStepsService) {}

    public function show(InsightsNextStepsRequest $request): JsonResponse
    {
        return response()->json($this->nextStepsService->nextSteps($request->user(), $request->categoryId()));
    }
}
