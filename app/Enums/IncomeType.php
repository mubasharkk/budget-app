<?php

namespace App\Enums;

enum IncomeType: string
{
    case Net = 'net';
    case Brutto = 'brutto';

    public function label(): string
    {
        return match ($this) {
            self::Net => 'Net (after tax)',
            self::Brutto => 'Gross (brutto)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Net => 'Net',
            self::Brutto => 'Brutto',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
