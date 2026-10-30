<?php

namespace App\Enums;

enum DealWeek: string
{
    case Current = 'current';
    case Upcoming = 'upcoming';

    public function label(): string
    {
        return match ($this) {
            self::Current => 'This week',
            self::Upcoming => 'Next week',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $week): array => ['value' => $week->value, 'label' => $week->label()],
            self::cases(),
        );
    }
}
