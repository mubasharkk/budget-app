<?php

namespace App\Enums;

/**
 * What a receipt line is. Only products count as consumption; deposits, bags,
 * discounts and fees are money on the receipt but nothing that is consumed.
 */
enum LineItemType: string
{
    case Product = 'product';
    case Deposit = 'deposit';
    case DepositReturn = 'deposit_return';
    case Bag = 'bag';
    case Discount = 'discount';
    case Fee = 'fee';

    public function isConsumption(): bool
    {
        return $this === self::Product;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
