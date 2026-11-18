<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumptionScope;
use App\Domain\Analytics\Exceptions\ConsumptionAnalysisFailed;
use App\Domain\Identity\Services\UserSettingsService;
use App\Domain\Shared\Services\LlmService;
use App\Models\ConsumptionAnalysis;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Has the AI write an analysis of what the user consumed. The figures are
 * calculated exactly here; the AI only turns them into prose and tips.
 */
class ConsumptionAnalysisService
{
    /**
     * How many items, by spend, the AI is shown.
     */
    public const ITEMS = 30;

    /**
     * How many past analyses are listed.
     */
    public const HISTORY = 10;

    public function __construct(
        private ConsumptionService $consumptionService,
        private UserSettingsService $userSettingsService,
        private LlmService $llmService,
    ) {}

    /**
     * @return Collection<int, ConsumptionAnalysis>
     */
    public function recent(User $user): Collection
    {
        return ConsumptionAnalysis::query()
            ->where('user_id', $user->id)
            ->latest()
            ->latest('id')
            ->limit(self::HISTORY)
            ->get(['id', 'period_start', 'period_end', 'summary', 'findings', 'tips', 'created_at']);
    }

    /**
     * Compare the period with the one of the same length before it and have
     * the AI write about it.
     *
     * @throws ConsumptionAnalysisFailed
     */
    public function analyse(User $user, CarbonImmutable $start, CarbonImmutable $end): ConsumptionAnalysis
    {
        $payload = $this->payload($user, $start, $end);

        if ($payload['items'] === []) {
            throw ConsumptionAnalysisFailed::nothingConsumed();
        }

        $result = $this->llmService->analyseConsumption($payload);
        $summary = trim((string) ($result['data']['summary'] ?? ''));

        if (! $result['success'] || $summary === '') {
            throw ConsumptionAnalysisFailed::aiUnavailable();
        }

        return ConsumptionAnalysis::query()->create([
            'user_id' => $user->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'summary' => $summary,
            'findings' => collect($result['data']['findings'] ?? [])
                ->filter(fn ($finding): bool => is_array($finding) && filled($finding['title'] ?? null))
                ->map(fn (array $finding): array => ['title' => (string) $finding['title'], 'detail' => (string) ($finding['detail'] ?? '')])
                ->values()
                ->all(),
            'tips' => collect($result['data']['tips'] ?? [])->filter(fn ($tip): bool => is_string($tip) && $tip !== '')->values()->all(),
            'payload' => $payload,
        ]);
    }

    /**
     * The exact figures the AI is given.
     *
     * @return array{currency: string, period: array{start: string, end: string}, previous_period: array{start: string, end: string}, totals: array{spend: float, previous_spend: float}, items: array<int, array<string, mixed>>}
     */
    public function payload(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $scope = new ConsumptionScope(excludedCategoryIds: $this->userSettingsService->excludedConsumptionCategoryIds($user));
        $days = (int) $start->diffInDays($end) + 1;
        $previousEnd = $start->subDay();
        $previousStart = $previousEnd->subDays($days - 1);

        $all = fn (CarbonImmutable $from, CarbonImmutable $to): Collection => $this->consumptionService
            ->topItems($user->id, 'spend', $from->toDateString(), $to->toDateString(), null, PHP_INT_MAX, $scope);

        $current = $all($start, $end);
        $previous = $all($previousStart, $previousEnd)->keyBy('key');

        return [
            'currency' => $user->default_currency ?? 'EUR',
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'previous_period' => ['start' => $previousStart->toDateString(), 'end' => $previousEnd->toDateString()],
            'totals' => [
                'spend' => round($current->sum('total_spend'), 2),
                'previous_spend' => round($previous->sum('total_spend'), 2),
            ],
            'items' => $current->take(self::ITEMS)->map(function (object $item) use ($previous): array {
                $before = $previous->get($item->key);

                return [
                    'name' => $item->item_name,
                    'category' => $item->category_name,
                    'purchases' => $item->purchase_count,
                    'amount' => $item->amount,
                    'unit' => $item->unit,
                    'spend' => $item->total_spend,
                    'price_per_unit' => $this->pricePerUnit($item),
                    'previous' => $before === null ? null : [
                        'purchases' => $before->purchase_count,
                        'amount' => $before->amount,
                        'spend' => $before->total_spend,
                        'price_per_unit' => $before->unit === $item->unit ? $this->pricePerUnit($before) : null,
                    ],
                ];
            })->values()->all(),
        ];
    }

    /**
     * Price per litre/kilo/piece, only when every purchase had a known size.
     */
    private function pricePerUnit(object $item): ?float
    {
        return $item->amount > 0 && $item->unmeasured_count === 0
            ? round($item->total_spend / $item->amount, 2)
            : null;
    }
}
