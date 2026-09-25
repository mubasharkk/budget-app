<?php

namespace App\Domain\Shared\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendar boundaries for the reporting periods used across domains
 * ('week' is Monday–Sunday; anything unrecognised falls back to 'month').
 */
final class PeriodRange
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function current(string $period, ?CarbonInterface $anchor = null): array
    {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());

        return match ($period) {
            'week' => [
                $anchor->startOfWeek(CarbonInterface::MONDAY),
                $anchor->endOfWeek(CarbonInterface::SUNDAY),
            ],
            'quarter' => [
                $anchor->startOfQuarter(),
                $anchor->endOfQuarter(),
            ],
            default => [
                $anchor->startOfMonth(),
                $anchor->endOfMonth(),
            ],
        };
    }

    /**
     * The period immediately before the one containing the anchor.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function previous(string $period, ?CarbonInterface $anchor = null): array
    {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());

        return match ($period) {
            'week' => self::current('week', $anchor->startOfWeek(CarbonInterface::MONDAY)->subWeek()),
            'quarter' => self::current('quarter', $anchor->startOfQuarter()->subQuarter()),
            default => self::current('month', $anchor->startOfMonth()->subMonth()),
        };
    }
}
