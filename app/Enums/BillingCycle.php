<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use DateTimeInterface;

enum BillingCycle: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    /**
     * Number of billing occurrences per year.
     */
    public function perYear(): float
    {
        return match ($this) {
            self::Weekly => 52.0,
            self::Biweekly => 26.0,
            self::Monthly => 12.0,
            self::Quarterly => 4.0,
            self::Yearly => 1.0,
        };
    }

    /**
     * Factor that converts a single charge of this cycle into its monthly equivalent.
     */
    public function toMonthlyFactor(): float
    {
        return $this->perYear() / 12.0;
    }

    /**
     * Normalize an amount billed on this cycle to its monthly equivalent.
     */
    public function toMonthlyAmount(float $amount): float
    {
        return round($amount * $this->toMonthlyFactor(), 2);
    }

    /**
     * Factor that converts a single charge of this cycle into its weekly equivalent.
     */
    public function toWeeklyFactor(): float
    {
        return $this->perYear() / 52.0;
    }

    /**
     * Normalize an amount billed on this cycle to its weekly equivalent.
     */
    public function toWeeklyAmount(float $amount): float
    {
        return round($amount * $this->toWeeklyFactor(), 2);
    }

    /**
     * Advance a date by one billing cycle.
     */
    public function nextDate(DateTimeInterface $from): CarbonImmutable
    {
        $date = CarbonImmutable::instance($from);

        return match ($this) {
            self::Weekly => $date->addWeek(),
            self::Biweekly => $date->addWeeks(2),
            self::Monthly => $date->addMonthNoOverflow(),
            self::Quarterly => $date->addMonthsNoOverflow(3),
            self::Yearly => $date->addYear(),
        };
    }

    /**
     * Human-readable label for UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Biweekly => 'Biweekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * Option list for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $cycle): array => ['value' => $cycle->value, 'label' => $cycle->label()],
            self::cases(),
        );
    }
}
