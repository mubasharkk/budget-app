<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumedLine;
use App\Domain\Deals\Services\DealCatalogService;
use App\Domain\Identity\Services\UserSettingsService;
use App\Models\ConsumableGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Data provider for the forward-looking part of Insights: what could cost
 * less, what is due, and what to correct. It follows the category and the
 * scope but not the period, because it is about now.
 */
class InsightsNextStepsService
{
    public const HISTORY_DAYS = 180;

    public const SAVINGS = 6;

    public const DUE_SOON = 8;

    /**
     * Supermarket offers are German, so only comparable for users counting in euros.
     */
    private const DEALS_CURRENCY = 'EUR';

    public function __construct(
        private ConsumptionService $consumptionService,
        private ConsumptionSavingsService $savingsService,
        private RepurchasePredictionService $predictionService,
        private ConsumptionDataQualityService $dataQualityService,
        private DealCatalogService $dealCatalogService,
        private UserSettingsService $userSettingsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function nextSteps(User $user, ?int $categoryId = null): array
    {
        $today = CarbonImmutable::today();
        $scope = $this->userSettingsService->consumptionScope($user);
        $lines = $this->consumptionService->recentLines($user->id, self::HISTORY_DAYS, $scope, $categoryId);

        $canCompareOffers = $scope->currency === self::DEALS_CURRENCY && $user->postal_code !== null;
        $deals = $canCompareOffers ? $this->dealCatalogService->currentDeals($user->postal_code) : collect();

        $priceFrom = $today->subDays(ConsumptionSavingsService::PRICE_DAYS);
        $found = collect($this->savingsService->find(
            $lines->filter(fn (ConsumedLine $line): bool => $line->date->gte($priceFrom)),
            $deals,
        ))->map(fn (array $row): array => [...$row, 'url' => $this->url($row['key'])]);

        $savingByKey = $found->keyBy('key');
        $due = collect($this->predictionService->dueSoon($lines, $today))
            ->take(self::DUE_SOON)
            ->map(fn (array $row): array => [...$row, 'url' => $this->url($row['key']), 'cheaper' => $savingByKey->get($row['key'])]);

        return [
            'savings' => [
                'offers_status' => match (true) {
                    $scope->currency !== self::DEALS_CURRENCY => 'other_currency',
                    $user->postal_code === null => 'no_postal_code',
                    $deals->isEmpty() => 'no_offers',
                    default => 'compared',
                },
                'total' => round($found->take(self::SAVINGS)->sum('saving'), 2),
                'rows' => $found->take(self::SAVINGS)->values()->all(),
            ],
            'due_soon' => [
                'total' => round($due->sum('estimate'), 2),
                'rows' => $due->values()->all(),
            ],
            'data_quality' => [
                ...$this->dataQualityService->gaps($lines),
                'group_names' => $this->groupNames($user),
            ],
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function groupNames(User $user): Collection
    {
        return ConsumableGroup::query()->where('user_id', $user->id)->orderBy('name')->pluck('name');
    }

    private function url(string $key): ?string
    {
        return str_starts_with($key, 'group:') ? route('insights.groups.show', (int) substr($key, 6)) : null;
    }
}
