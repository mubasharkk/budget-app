<?php

namespace Tests\Unit;

use App\Enums\AnalysisPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnalysisPeriodTest extends TestCase
{
    /**
     * @return array<string, array{0: AnalysisPeriod, 1: string, 2: array{0: string, 1: string, 2: string, 3: string}}>
     */
    public static function windows(): array
    {
        return [
            'this month, against the same stretch of last month' => [AnalysisPeriod::ThisMonth, '2026-11-18', ['2026-11-01', '2026-11-18', '2026-10-01', '2026-10-18']],
            'this month, capped at a shorter last month' => [AnalysisPeriod::ThisMonth, '2026-03-31', ['2026-03-01', '2026-03-31', '2026-02-01', '2026-02-28']],
            'last month, against the whole month before' => [AnalysisPeriod::LastMonth, '2026-03-31', ['2026-02-01', '2026-02-28', '2026-01-01', '2026-01-31']],
            'last 3 months, against the same number of days before' => [AnalysisPeriod::LastThreeMonths, '2026-11-18', ['2026-08-19', '2026-11-18', '2026-05-19', '2026-08-18']],
            'last 6 months, against the 184 days before' => [AnalysisPeriod::LastSixMonths, '2026-11-18', ['2026-05-19', '2026-11-18', '2025-11-16', '2026-05-18']],
            'this year, against the same stretch of last year' => [AnalysisPeriod::ThisYear, '2026-11-18', ['2026-01-01', '2026-11-18', '2025-01-01', '2025-11-18']],
            'last 12 months' => [AnalysisPeriod::LastTwelveMonths, '2026-11-18', ['2025-11-19', '2026-11-18', '2024-11-19', '2025-11-18']],
            'last year, against the year before' => [AnalysisPeriod::LastYear, '2026-11-18', ['2025-01-01', '2025-12-31', '2024-01-01', '2024-12-31']],
        ];
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string}  $expected
     */
    #[DataProvider('windows')]
    public function test_each_period_has_its_dates_and_what_it_is_compared_with(AnalysisPeriod $period, string $today, array $expected): void
    {
        $window = $period->window(CarbonImmutable::parse($today));

        $this->assertSame($expected, [
            $window['start']->toDateString(),
            $window['end']->toDateString(),
            $window['previous_start']->toDateString(),
            $window['previous_end']->toDateString(),
        ]);
    }
}
