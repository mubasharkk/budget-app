<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumptionScope;
use App\Domain\Identity\Services\UserSettingsService;
use App\Domain\Products\Support\ProductQuantity;
use App\Enums\AnalysisPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Data provider for the Insights page: one period and category drive the
 * headline figures, the monthly chart, what changed, the items and the shops.
 * Rows are display-ready; keep grouping and maths here, not in the components.
 */
class InsightsOverviewService
{
    public const CHART_MONTHS = 12;

    public const COLOUR_SLOTS = 8;

    public const MOVERS = 8;

    public const SHOPS = 8;

    /**
     * Days without records at the start of a period before it counts as only
     * partly recorded: the first shop of a month is rarely on the 1st.
     */
    public const RECORDS_GRACE_DAYS = 7;

    public function __construct(
        private ConsumptionService $consumptionService,
        private ConsumptionChangeService $changeService,
        private UserSettingsService $userSettingsService,
    ) {}

    /**
     * @param  array{label: string, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}  $window
     * @return array<string, mixed>
     */
    public function overview(User $user, array $window, ?int $categoryId = null): array
    {
        $scope = $this->userSettingsService->consumptionScope($user);
        ['start' => $start, 'end' => $end, 'previous_start' => $previousStart, 'previous_end' => $previousEnd] = $window;

        $current = $this->consumed($user->id, $start, $end, $categoryId, $scope);
        $previous = $this->consumed($user->id, $previousStart, $previousEnd, $categoryId, $scope);
        $explained = $this->changeService->explain($current, $previous);

        $spend = round($current->sum('total_spend'), 2);
        $previousSpend = round($previous->sum('total_spend'), 2);
        $hasComparison = $previous->isNotEmpty();
        $recordsStart = $this->consumptionService->firstConsumedOn($user->id, $scope);
        $isPartlyRecorded = $recordsStart !== null && $recordsStart->gt($previousStart->addDays(self::RECORDS_GRACE_DAYS));
        $days = (int) $start->diffInDays($end) + 1;

        return [
            'window' => [
                'label' => $window['label'],
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'previous_start' => $previousStart->toDateString(),
                'previous_end' => $previousEnd->toDateString(),
            ],
            'records_start' => $hasComparison && $isPartlyRecorded ? $recordsStart->toDateString() : null,
            'periods' => AnalysisPeriod::options(),
            'scope' => [
                'include_business' => $scope->includeBusiness,
                'excluded_category_ids' => $scope->excludedCategoryIds,
            ],
            'totals' => [
                'spend' => $spend,
                'previous_spend' => $hasComparison ? $previousSpend : null,
                'change' => $hasComparison ? round($spend - $previousSpend, 2) : null,
                'change_percent' => $hasComparison && $previousSpend > 0
                    ? round(($spend - $previousSpend) / $previousSpend * 100, 1)
                    : null,
                'per_day' => round($spend / max($days, 1), 2),
                'products' => $current->count(),
                'purchases' => (int) $current->sum('purchase_count'),
                'forecast' => $isPartlyRecorded
                    ? null
                    : $this->forecast($user->id, $window, $categoryId, $scope, $spend, $previousSpend),
                'price_change_percent' => $explained['price_change_percent'],
            ],
            'changes' => [
                'effects' => $hasComparison ? $explained['effects'] : null,
                'movers' => $hasComparison ? $this->movers($explained['items']) : [],
            ],
            'chart' => $this->chart($user->id, $window, $categoryId, $scope),
            'items' => $this->items($current, collect($explained['items'])->keyBy('key'), $spend, $hasComparison),
            'shops' => $this->consumptionService->consumptionVendors(
                $user->id, $start->toDateString(), $end->toDateString(), $categoryId, self::SHOPS, $scope,
            ),
            'coverage' => $this->coverage($current, $spend),
            'other_currencies' => $this->consumptionService->otherCurrencies(
                $user->id, $start->toDateString(), $end->toDateString(), $categoryId, $scope,
            ),
            'other_currency_contracts' => $this->consumptionService->otherCurrencyContracts($user->id, $categoryId, $scope),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function consumed(int $userId, CarbonImmutable $start, CarbonImmutable $end, ?int $categoryId, ConsumptionScope $scope): Collection
    {
        return $this->consumptionService->topItems(
            $userId, 'spend', $start->toDateString(), $end->toDateString(), $categoryId, PHP_INT_MAX, $scope,
        );
    }

    /**
     * For the month or year in progress: what is spent so far plus what the
     * rest of the one before cost. That copes with one big weekly or monthly
     * shop, where a daily average would not. Null for any other period.
     *
     * @param  array{label: string, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}  $window
     * @return array{total: float, until: string}|null
     */
    private function forecast(int $userId, array $window, ?int $categoryId, ConsumptionScope $scope, float $spend, float $previousSpend): ?array
    {
        ['start' => $start, 'end' => $end, 'previous_start' => $previousStart] = $window;
        $today = CarbonImmutable::today();

        if (! $end->isSameDay($today)) {
            return null;
        }

        [$until, $previousUntil] = match (true) {
            $start->isSameDay($today->startOfYear()) && $previousStart->isSameDay($start->subYear()) => [$start->endOfYear(), $previousStart->endOfYear()],
            $start->isSameDay($today->startOfMonth()) && $previousStart->isSameDay($start->subMonthNoOverflow()) => [$start->endOfMonth(), $previousStart->endOfMonth()],
            default => [null, null],
        };

        if ($until === null) {
            return null;
        }

        $wholePrevious = $this->consumed($userId, $previousStart, $previousUntil, $categoryId, $scope)->sum('total_spend');

        if ($wholePrevious <= 0) {
            return null;
        }

        return ['total' => round($spend + $wholePrevious - $previousSpend, 2), 'until' => $until->toDateString()];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function movers(array $items): array
    {
        return collect($items)
            ->filter(fn (array $row): bool => abs($row['change']) >= 0.01)
            ->take(self::MOVERS)
            ->map(fn (array $row): array => [...$row, 'url' => $this->url($row['key']), 'reason' => $this->reason($row)])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function reason(array $row): string
    {
        return match ($row['kind']) {
            'new' => 'Not bought in the earlier period',
            'stopped' => 'Not bought in this period',
            'unclear' => 'Different products or unknown pack sizes',
            'amount' => $row['amount_effect'] >= 0 ? 'Mostly because you bought more' : 'Mostly because you bought less',
            'price' => ($row['price_effect'] >= 0 ? 'Mostly because you paid more per ' : 'Mostly because you paid less per ').($row['unit'] === 'pcs' ? 'piece' : $row['unit']),
        };
    }

    /**
     * The months up to the period's end, each category keeping its colour.
     *
     * @param  array{label: string, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}  $window
     * @return array{months: array<int, array<string, mixed>>, series: array<int, array<string, mixed>>}
     */
    private function chart(int $userId, array $window, ?int $categoryId, ConsumptionScope $scope): array
    {
        $first = $window['end']->startOfMonth()->subMonths(self::CHART_MONTHS - 1);
        $today = CarbonImmutable::today();
        $slots = $this->consumptionService->categoryColourSlots($userId, self::COLOUR_SLOTS);

        $months = array_map(function (int $offset) use ($first, $window, $today): array {
            $month = $first->addMonths($offset);

            return [
                'label' => $month->format('M y'),
                'start' => $month->toDateString(),
                'end' => $month->endOfMonth()->min($today)->toDateString(),
                'is_current' => $month->isSameMonth($today),
                'in_period' => $month->lte($window['end']) && $month->endOfMonth()->gte($window['start']),
            ];
        }, range(0, self::CHART_MONTHS - 1));

        $series = collect($this->consumptionService->monthlySeries($userId, $first, self::CHART_MONTHS, $scope, $categoryId))
            ->map(fn (array $row): array => [...$row, 'colour' => $slots[$row['key']] ?? null])
            ->sortBy(fn (array $row): int => $row['colour'] ?? self::COLOUR_SLOTS)
            ->values()
            ->all();

        return ['months' => $months, 'series' => $series];
    }

    /**
     * @param  Collection<int, object>  $current
     * @param  Collection<string, array<string, mixed>>  $explained
     * @return array<int, array<string, mixed>>
     */
    private function items(Collection $current, Collection $explained, float $spend, bool $hasComparison): array
    {
        return $current->map(function (object $item) use ($explained, $spend, $hasComparison): array {
            $change = $explained->get($item->key);
            $isMeasured = $item->amount > 0 && $item->unmeasured_count === 0;

            return [
                'key' => $item->key,
                'label' => $item->item_name,
                'url' => $this->url($item->key),
                'category' => $item->category_name,
                'products' => array_values(array_diff($item->variants, [$item->item_name])),
                'amount_label' => $item->amount !== null ? ProductQuantity::label($item->amount, $item->unit) : null,
                'unmeasured_count' => $item->unmeasured_count,
                'purchases' => $item->purchase_count,
                'spend' => $item->total_spend,
                'share_percent' => $spend > 0 ? round($item->total_spend / $spend * 100, 1) : 0.0,
                'unit' => $isMeasured ? $item->unit : null,
                'unit_price' => $isMeasured ? round($item->total_spend / $item->amount, 2) : null,
                'change' => $hasComparison ? $change['change'] : null,
                'is_new' => $hasComparison && $change['kind'] === 'new',
            ];
        })->values()->all();
    }

    /**
     * How much of the spend the per-product and per-unit figures really cover.
     *
     * @param  Collection<int, object>  $current
     * @return array{named_percent: int, measured_percent: int}|null
     */
    private function coverage(Collection $current, float $spend): ?array
    {
        if ($spend <= 0) {
            return null;
        }

        $share = fn (Collection $items): int => (int) round($items->sum('total_spend') / $spend * 100);

        return [
            'named_percent' => $share($current->filter(fn (object $item): bool => str_starts_with($item->key, 'group:'))),
            'measured_percent' => $share($current->filter(fn (object $item): bool => $item->amount > 0 && $item->unmeasured_count === 0)),
        ];
    }

    private function url(string $key): ?string
    {
        return str_starts_with($key, 'group:') ? route('insights.groups.show', (int) substr($key, 6)) : null;
    }
}
