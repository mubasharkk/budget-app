<?php

namespace App\Enums;

/**
 * Optional dashboard sections a user can add or remove. "At a glance" and
 * "Income vs spending" are always shown and are deliberately not listed here.
 */
enum DashboardSection: string
{
    case Transactions = 'transactions';
    case ExpenseOverview = 'expense_overview';
    case ItemsConsumed = 'items_consumed';
    case BudgetVsActual = 'budget_vs_actual';
    case MostBoughtItems = 'most_bought_items';

    public function label(): string
    {
        return match ($this) {
            self::Transactions => 'Income & expense list',
            self::ExpenseOverview => 'Expense overview',
            self::ItemsConsumed => 'Items consumed',
            self::BudgetVsActual => 'Budget vs actual',
            self::MostBoughtItems => 'Most bought items',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $section): array => ['value' => $section->value, 'label' => $section->label()],
            self::cases(),
        );
    }
}
