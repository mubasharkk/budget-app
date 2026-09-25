<?php

namespace App\Domain\Analytics\Data;

use App\Domain\Shared\Support\PeriodRange;

/**
 * Sanitised options for the consumption report. A named period (week/month/quarter)
 * takes precedence over an explicit start/end date range.
 */
final readonly class ConsumptionReportFilters
{
    public const LIMIT_OPTIONS = [10, 20, 50, 100];

    public const PERIODS = ['week', 'month', 'quarter'];

    public function __construct(
        public ?string $period,
        public ?string $startDate,
        public ?string $endDate,
        public ?int $categoryId,
        public int $limit,
        public string $metric,
        public int $year,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $limit = (int) ($input['limit'] ?? 10);
        $period = null;
        $startDate = $input['start_date'] ?? null;
        $endDate = $input['end_date'] ?? null;

        if (filled($input['period'] ?? null)) {
            $period = in_array($input['period'], self::PERIODS, true) ? $input['period'] : 'month';
            [$start, $end] = PeriodRange::current($period);
            $startDate = $start->toDateString();
            $endDate = $end->toDateString();
        }

        return new self(
            period: $period,
            startDate: $startDate,
            endDate: $endDate,
            categoryId: ($input['category_id'] ?? null) ? (int) $input['category_id'] : null,
            limit: in_array($limit, self::LIMIT_OPTIONS, true) ? $limit : 10,
            metric: ($input['metric'] ?? null) === 'spend' ? 'spend' : 'quantity',
            year: (int) (($input['year'] ?? null) ?: now()->year),
        );
    }
}
