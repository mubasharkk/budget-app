<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * Preset periods for the consumption analysis, each with the period it is
 * compared with. A period still in progress (this month, this year) is set
 * against the same stretch of the one before; a finished one against the
 * whole one before; a rolling one against the same number of days before it.
 */
enum AnalysisPeriod: string
{
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case LastThreeMonths = 'last_3_months';
    case LastSixMonths = 'last_6_months';
    case ThisYear = 'this_year';
    case LastTwelveMonths = 'last_12_months';
    case LastYear = 'last_year';

    public function label(): string
    {
        return match ($this) {
            self::ThisMonth => 'This month',
            self::LastMonth => 'Last month',
            self::LastThreeMonths => 'Last 3 months',
            self::LastSixMonths => 'Last 6 months',
            self::ThisYear => 'This year',
            self::LastTwelveMonths => 'Last 12 months',
            self::LastYear => 'Last year',
        };
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}
     */
    public function window(CarbonImmutable $today): array
    {
        $today = $today->startOfDay();

        return match ($this) {
            self::ThisMonth => self::sameStretch($today->startOfMonth(), $today, $today->startOfMonth()->subMonthNoOverflow()),
            self::LastMonth => self::whole(
                $today->startOfMonth()->subMonthNoOverflow(),
                $today->startOfMonth()->subDay(),
                $today->startOfMonth()->subMonthsNoOverflow(2),
                $today->startOfMonth()->subMonthNoOverflow()->subDay(),
            ),
            self::LastThreeMonths => self::rolling($today->subMonthsNoOverflow(3)->addDay(), $today),
            self::LastSixMonths => self::rolling($today->subMonthsNoOverflow(6)->addDay(), $today),
            self::ThisYear => self::sameStretch($today->startOfYear(), $today, $today->startOfYear()->subYear()),
            self::LastTwelveMonths => self::rolling($today->subYear()->addDay(), $today),
            self::LastYear => self::whole(
                $today->startOfYear()->subYear(),
                $today->startOfYear()->subDay(),
                $today->startOfYear()->subYears(2),
                $today->startOfYear()->subYear()->subDay(),
            ),
        };
    }

    /**
     * A custom date range, compared with the same number of days before it.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}
     */
    public static function rolling(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = (int) $start->diffInDays($end) + 1;
        $previousEnd = $start->subDay();

        return self::whole($start, $end, $previousEnd->subDays($days - 1), $previousEnd);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $period): array => ['value' => $period->value, 'label' => $period->label()], self::cases());
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}
     */
    private static function sameStretch(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $previousStart): array
    {
        $elapsed = (int) $start->diffInDays($end);

        return self::whole($start, $end, $previousStart, $previousStart->addDays($elapsed)->min($start->subDay()));
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}
     */
    private static function whole(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $previousStart, CarbonImmutable $previousEnd): array
    {
        return ['start' => $start, 'end' => $end, 'previous_start' => $previousStart, 'previous_end' => $previousEnd];
    }
}
