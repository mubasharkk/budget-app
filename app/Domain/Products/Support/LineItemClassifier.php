<?php

namespace App\Domain\Products\Support;

use App\Enums\LineItemType;

/**
 * Guesses a receipt line's type from its name, total and category, for lines
 * the AI has not labelled (receipts read before line types existed).
 */
final class LineItemClassifier
{
    private const DEPOSIT_RETURN = '/\b(leergut|pfandrückgabe|pfandbon|pfand\s*-?\s*rückgabe|deposit\s+return|bottle\s+return)\b/iu';

    private const DEPOSIT = '/\b(pfand|einwegpfand|mehrwegpfand|deposit)\b/iu';

    private const BAG = '/\b(tüte|tuete|tragetasche|einkaufstasche|papiertüte|plastiktüte|tragetüte|carrier\s+bag|shopping\s+bag)\b/iu';

    private const DISCOUNT = '/\b(rabatt|nachlass|coupon|gutschein|discount|preisvorteil|aktion\s*-|sofortrabatt)\b/iu';

    private const FEE = '/\b(liefergebühr|servicegebühr|versand|versandkosten|delivery\s+fee|service\s+fee|trinkgeld)\b/iu';

    public static function classify(string $name, float $total, ?string $categoryName = null): LineItemType
    {
        if (preg_match(self::DEPOSIT_RETURN, $name)) {
            return LineItemType::DepositReturn;
        }

        if (preg_match(self::DEPOSIT, $name) || ($categoryName !== null && preg_match('/^(deposits?|pfand)$/iu', trim($categoryName)))) {
            return $total < 0 ? LineItemType::DepositReturn : LineItemType::Deposit;
        }

        if ($total < 0 || preg_match(self::DISCOUNT, $name)) {
            return LineItemType::Discount;
        }

        if (preg_match(self::BAG, $name)) {
            return LineItemType::Bag;
        }

        if (preg_match(self::FEE, $name)) {
            return LineItemType::Fee;
        }

        return LineItemType::Product;
    }
}
