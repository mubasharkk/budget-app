<?php

namespace App\Enums;

enum BudgetPeriod: string
{
    case Monthly = 'monthly';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Weekly => 'Weekly',
        };
    }

    /**
     * Period key used by ExpenseService ('month' or 'week').
     */
    public function toExpensePeriod(): string
    {
        return $this === self::Weekly ? 'week' : 'month';
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $period): array => ['value' => $period->value, 'label' => $period->label()],
            self::cases(),
        );
    }
}
