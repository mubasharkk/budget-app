<?php

namespace App\Enums;

enum DealSort: string
{
    case Saving = 'saving';
    case Price = 'price';
    case UnitPrice = 'unit_price';
    case Ending = 'ending';

    public function label(): string
    {
        return match ($this) {
            self::Saving => 'Biggest saving',
            self::Price => 'Lowest price',
            self::UnitPrice => 'Lowest price per kg/l',
            self::Ending => 'Ending soonest',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $sort): array => ['value' => $sort->value, 'label' => $sort->label()],
            self::cases(),
        );
    }
}
