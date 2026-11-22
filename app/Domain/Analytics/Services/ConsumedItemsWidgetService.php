<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumptionReportFilters;
use App\Domain\Analytics\Data\ConsumptionScope;
use App\Domain\Products\Support\ProductQuantity;
use App\Domain\Shared\Support\PeriodRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Data for the dashboard's "Items consumed" widget: what the user consumed in
 * the period under its general name ("Milk"), with the products merged into
 * it, the amount in its unit, its share of the spend, the change against the
 * period of the same length before, and a link to its Insights page.
 */
class ConsumedItemsWidgetService
{
    public function __construct(private ConsumptionService $consumptionService) {}

    /**
     * @return array{period: ?string, start: ?string, end: ?string, totals: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function summary(int $userId, ConsumptionReportFilters $filters, ConsumptionScope $scope): array
    {
        $consumed = fn (?string $start, ?string $end): Collection => $this->consumptionService->topItems(
            $userId, $filters->metric, $start, $end, $filters->categoryId, PHP_INT_MAX, $scope,
        );

        $all = $consumed($filters->startDate, $filters->endDate);
        $previousRange = $this->previousRange($filters);
        $previous = $previousRange !== null ? $consumed(...$previousRange)->keyBy('key') : null;

        $spend = round($all->sum('total_spend'), 2);
        $previousSpend = $previous !== null ? round($previous->sum('total_spend'), 2) : null;

        return [
            'period' => $filters->period,
            'start' => $filters->startDate,
            'end' => $filters->endDate,
            'totals' => [
                'spend' => $spend,
                'previous_spend' => $previousSpend,
                'change_percent' => $this->changePercent($spend, $previousSpend),
                'purchases' => (int) $all->sum('purchase_count'),
                'labels' => $all->count(),
            ],
            'other_currencies' => $this->consumptionService->otherCurrencies(
                $userId, $filters->startDate, $filters->endDate, $filters->categoryId, $scope,
            ),
            'items' => $this->rows($all->take($filters->limit), $spend, $previous),
        ];
    }

    /**
     * @param  Collection<int, object>  $items
     * @param  Collection<string, object>|null  $previous
     * @return array<int, array<string, mixed>>
     */
    private function rows(Collection $items, float $totalSpend, ?Collection $previous): array
    {
        return $items->map(function (object $item) use ($totalSpend, $previous): array {
            $products = array_values(array_filter($item->variants, fn (string $name): bool => $name !== $item->item_name));
            $groupId = str_starts_with($item->key, 'group:') ? (int) substr($item->key, 6) : null;
            $previousSpend = $previous !== null ? (float) ($previous->get($item->key)?->total_spend ?? 0) : null;

            return [
                'key' => $item->key,
                'label' => $item->item_name,
                'url' => $groupId !== null ? route('insights.groups.show', $groupId) : null,
                'category' => $item->category_name,
                'products' => $products,
                'amount' => $item->amount,
                'unit' => $item->unit,
                'amount_label' => $item->amount !== null ? ProductQuantity::label($item->amount, $item->unit) : null,
                'unmeasured_count' => $item->unmeasured_count,
                'spend' => $item->total_spend,
                'share_percent' => $totalSpend > 0 ? round($item->total_spend / $totalSpend * 100, 1) : 0.0,
                'previous_spend' => $previousSpend,
                'change_percent' => $this->changePercent($item->total_spend, $previousSpend),
                'is_new' => $previousSpend === 0.0,
                'purchases' => $item->purchase_count,
            ];
        })->values()->all();
    }

    /**
     * What to compare with. A week, month or quarter is compared with the same
     * stretch of the one before (1–18 November with 1–18 October), so a period
     * still in progress is not set against a whole one; a date range with the
     * same number of days right before it.
     *
     * @return array{0: string, 1: string}|null
     */
    private function previousRange(ConsumptionReportFilters $filters): ?array
    {
        if ($filters->startDate === null || $filters->endDate === null) {
            return null;
        }

        $start = CarbonImmutable::parse($filters->startDate);
        $end = CarbonImmutable::parse($filters->endDate)->min(CarbonImmutable::today());

        if ($filters->period !== null) {
            [$previousStart, $previousEnd] = PeriodRange::previous($filters->period);
            $elapsed = (int) $start->diffInDays($end);

            return [
                $previousStart->toDateString(),
                $previousStart->addDays($elapsed)->min($previousEnd)->toDateString(),
            ];
        }

        $days = (int) $start->diffInDays($end) + 1;
        $previousEnd = $start->subDay();

        return [$previousEnd->subDays($days - 1)->toDateString(), $previousEnd->toDateString()];
    }

    /**
     * Percentage change, or null when there is nothing to compare with.
     */
    private function changePercent(float $current, ?float $previous): ?float
    {
        return $previous !== null && $previous > 0
            ? round(($current - $previous) / $previous * 100, 1)
            : null;
    }
}
