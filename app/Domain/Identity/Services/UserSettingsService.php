<?php

namespace App\Domain\Identity\Services;

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
