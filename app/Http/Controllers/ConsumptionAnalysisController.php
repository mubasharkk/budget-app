<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Exceptions\ConsumptionAnalysisFailed;
use App\Domain\Analytics\Services\ConsumptionAnalysisService;
use App\Enums\AnalysisPeriod;
use App\Http\Requests\ConsumptionAnalysisRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI-written analyses of the user's consumption on the Insights page.
 */
class ConsumptionAnalysisController extends Controller
{
    public function __construct(private ConsumptionAnalysisService $analysisService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'analyses' => $this->analysisService->recent($request->user()),
            'periods' => AnalysisPeriod::options(),
            'default_period' => ConsumptionAnalysisRequest::DEFAULT_PERIOD->value,
        ]);
    }

    public function store(ConsumptionAnalysisRequest $request): JsonResponse
    {
        try {
            $analysis = $this->analysisService->analyse($request->user(), $request->window(), $request->categoryId());
        } catch (ConsumptionAnalysisFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['analysis' => $this->analysisService->present($analysis)], 201);
    }
}
