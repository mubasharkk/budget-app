<?php

namespace App\Enums;

/**
 * Optional dashboard sections a user can add or remove. "At a glance" and
 * "Income vs spending" are always shown and are deliberately not listed here.
 */
enum DashboardSection: string
{
    case Transactions = 'transactions';
    case UpcomingBills = 'upcoming_bills';
    case ExpenseOverview = 'expense_overview';
    case ItemsConsumed = 'items_consumed';
    case BudgetVsActual = 'budget_vs_actual';
    case MostBoughtItems = 'most_bought_items';
    case DuplicateReceipts = 'duplicate_receipts';

    public function label(): string
    {
        return match ($this) {
            self::Transactions => 'Income & expense list',
            self::UpcomingBills => 'Upcoming bills',
            self::ExpenseOverview => 'Expense overview',
            self::ItemsConsumed => 'Items consumed',
            self::BudgetVsActual => 'Budget vs actual',
            self::MostBoughtItems => 'Most bought items',
            self::DuplicateReceipts => 'Possible duplicate receipts',
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
