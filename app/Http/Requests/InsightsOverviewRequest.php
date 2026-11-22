<?php

namespace App\Http\Requests;

use App\Enums\AnalysisPeriod;

/**
 * The Insights page's period and category: the same options as an analysis,
 * so both always show the same figures, but opening on this month.
 */
class InsightsOverviewRequest extends ConsumptionAnalysisRequest
{
    public const DEFAULT_PERIOD = AnalysisPeriod::ThisMonth;
}
