<?php

namespace App\Domain\Identity\Services;

use App\Domain\Analytics\Data\ConsumptionScope;
use App\Enums\DashboardSection;
use App\Models\User;

class UserSettingsService
{
    /**
     * The optional dashboard sections the user has switched on, in display order.
     * Unknown or stale keys in the stored settings are ignored.
     *
     * @return array<int, string>
     */
    public function dashboardSections(User $user): array
    {
        $stored = data_get($user->settings, 'dashboard.sections', []);

        return $this->normaliseSections(is_array($stored) ? $stored : []);
    }

    /**
     * Save the chosen optional sections under settings.dashboard.sections,
     * leaving any other settings untouched.
     *
     * @param  array<int, string>  $sections
     * @return array<int, string>
     */
    public function updateDashboardSections(User $user, array $sections): array
    {
        $sections = $this->normaliseSections($sections);

        $settings = $user->settings ?? [];
        data_set($settings, 'dashboard.sections', $sections);

        $user->settings = $settings;
        $user->save();

        return $sections;
    }

    /**
     * Categories the user does not count as consumption on the Insights page.
     *
     * @return array<int, int>
     */
    public function excludedConsumptionCategoryIds(User $user): array
    {
        $stored = data_get($user->settings, 'insights.excluded_category_ids', []);

        return $this->normaliseIds(is_array($stored) ? $stored : []);
    }

    /**
     * Save the excluded categories under settings.insights.excluded_category_ids,
     * leaving any other settings untouched.
     *
     * @param  array<int, mixed>  $categoryIds
     * @return array<int, int>
     */
    public function updateExcludedConsumptionCategoryIds(User $user, array $categoryIds): array
    {
        $categoryIds = $this->normaliseIds($categoryIds);

        $settings = $user->settings ?? [];
        data_set($settings, 'insights.excluded_category_ids', $categoryIds);

        $user->settings = $settings;
        $user->save();

        return $categoryIds;
    }

    /**
     * Whether business receipts count as consumption on the Insights page.
     */
    public function consumptionIncludesBusiness(User $user): bool
    {
        return (bool) data_get($user->settings, 'insights.include_business', false);
    }

    /**
     * What counts as consumption for the user: their excluded categories and
     * whether business receipts count.
     */
    public function consumptionScope(User $user): ConsumptionScope
    {
        return new ConsumptionScope(
            includeBusiness: $this->consumptionIncludesBusiness($user),
            excludedCategoryIds: $this->excludedConsumptionCategoryIds($user),
        );
    }

    /**
     * Save what counts as consumption under settings.insights, leaving any
     * other settings untouched.
     *
     * @param  array<int, mixed>  $excludedCategoryIds
     * @return array{excluded_category_ids: array<int, int>, include_business: bool}
     */
    public function updateConsumptionSettings(User $user, array $excludedCategoryIds, bool $includeBusiness): array
    {
        $excludedCategoryIds = $this->normaliseIds($excludedCategoryIds);

        $settings = $user->settings ?? [];
        data_set($settings, 'insights.excluded_category_ids', $excludedCategoryIds);
        data_set($settings, 'insights.include_business', $includeBusiness);

        $user->settings = $settings;
        $user->save();

        return ['excluded_category_ids' => $excludedCategoryIds, 'include_business' => $includeBusiness];
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function normaliseIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<int, string>
     */
    private function normaliseSections(array $sections): array
    {
        return array_values(array_filter(
            array_map(fn (DashboardSection $section): string => $section->value, DashboardSection::cases()),
            fn (string $value): bool => in_array($value, $sections, true),
        ));
    }
}
