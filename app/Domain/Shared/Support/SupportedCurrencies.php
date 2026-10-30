<?php

namespace App\Domain\Shared\Support;

use App\Models\User;
use Illuminate\Support\Number;

final class SupportedCurrencies
{
    public const CODES = ['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP'];

    public const FALLBACK = 'EUR';

    /**
     * The country whose number pattern each currency is shown in. Keep in step with
     * CURRENCY_LOCALES in resources/js/utils/money.js so both render identically.
     */
    private const LOCALES = [
        'EUR' => 'de_DE',
        'USD' => 'en_US',
        'INR' => 'en_IN',
        'PKR' => 'en_PK',
        'TRY' => 'tr_TR',
        'GBP' => 'en_GB',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::CODES;
    }

    /**
     * Locale whose pattern the currency is formatted in (e.g. 1.234,56 € vs $1,234.56).
     */
    public static function locale(?string $code): string
    {
        return self::LOCALES[$code ?? self::FALLBACK] ?? self::LOCALES[self::FALLBACK];
    }

    /**
     * Format an amount in the currency's own country pattern.
     */
    public static function format(float|int|string|null $amount, ?string $code = null): string
    {
        $code = $code ?: self::FALLBACK;

        return (string) Number::currency((float) $amount, in: $code, locale: self::locale($code));
    }

    /**
     * The currency a user has chosen for totals and new records.
     */
    public static function codeForUser(int $userId): string
    {
        return User::query()->whereKey($userId)->value('default_currency') ?? self::FALLBACK;
    }

    /**
     * The `in:` validation rule fragment for currency fields.
     */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::CODES);
    }
}
