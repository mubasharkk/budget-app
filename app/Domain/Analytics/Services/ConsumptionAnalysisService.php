<?php

namespace App\Domain\Analytics\Services;

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
     * @return Collection<int, array<string, mixed>>
     */
    public function recent(User $user): Collection
    {
        return ConsumptionAnalysis::query()
            ->where('user_id', $user->id)
            ->latest()
            ->latest('id')
            ->limit(self::HISTORY)
            ->get()
            ->toBase()
            ->map(fn (ConsumptionAnalysis $analysis): array => $this->present($analysis));
    }

    /**
     * An analysis for the page, with the spend it was written from so the page
     * can tell when the figures have changed since.
     *
     * @return array<string, mixed>
     */
    public function present(ConsumptionAnalysis $analysis): array
    {
        return [
            ...$analysis->only(['id', 'period_label', 'summary', 'findings', 'tips', 'created_at']),
            'period_start' => $analysis->period_start->toDateString(),
            'period_end' => $analysis->period_end->toDateString(),
            'spend' => data_get($analysis->payload, 'totals.spend'),
        ];
    }

    /**
     * Compare the period with the one before it and have the AI write about it.
     *
     * @param  array{label: string, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}  $window
     *
     * @throws ConsumptionAnalysisFailed
     */
    public function analyse(User $user, array $window, ?int $categoryId = null): ConsumptionAnalysis
    {
        $payload = $this->payload($user, $window, $categoryId);

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
            'period_label' => $window['label'],
            'period_start' => $window['start']->toDateString(),
            'period_end' => $window['end']->toDateString(),
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
     * @param  array{label: string, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}  $window
     * @return array{currency: string, label: string, period: array{start: string, end: string}, previous_period: array{start: string, end: string}, totals: array{spend: float, previous_spend: float}, items: array<int, array<string, mixed>>}
     */
    public function payload(User $user, array $window, ?int $categoryId = null): array
    {
        $scope = $this->userSettingsService->consumptionScope($user);
        ['start' => $start, 'end' => $end, 'previous_start' => $previousStart, 'previous_end' => $previousEnd] = $window;

        $all = fn (CarbonImmutable $from, CarbonImmutable $to): Collection => $this->consumptionService
            ->topItems($user->id, 'spend', $from->toDateString(), $to->toDateString(), $categoryId, PHP_INT_MAX, $scope);

        $current = $all($start, $end);
        $previous = $all($previousStart, $previousEnd)->keyBy('key');

        return [
            'currency' => $user->default_currency ?? 'EUR',
            'label' => $window['label'],
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
