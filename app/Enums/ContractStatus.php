<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether contracts in this state contribute to recurring spend.
     */
    public function isBillable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Option list for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
