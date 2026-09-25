<?php

namespace App\Domain\Shared\Support;

final class SupportedCurrencies
{
    public const CODES = ['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP'];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::CODES;
    }

    /**
     * The `in:` validation rule fragment for currency fields.
     */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::CODES);
    }
}
