<?php

namespace App\Domain\Shared\Support;

use App\Models\User;

final class SupportedCurrencies
{
    public const CODES = ['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP'];

    public const FALLBACK = 'EUR';

    private const SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'INR' => '₹',
        'PKR' => '₨',
        'TRY' => '₺',
        'GBP' => '£',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::CODES;
    }

    /**
     * Symbol for a currency code; unknown codes are shown as the code itself.
     */
    public static function symbol(?string $code): string
    {
        $code ??= self::FALLBACK;

        return self::SYMBOLS[$code] ?? $code.' ';
    }

    /**
     * The currency a user has chosen for totals and new records.
     */
    public static function codeForUser(int $userId): string
    {
        return User::query()->whereKey($userId)->value('default_currency') ?? self::FALLBACK;
    }

    public static function symbolForUser(int $userId): string
    {
        return self::symbol(self::codeForUser($userId));
    }

    /**
     * The `in:` validation rule fragment for currency fields.
     */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::CODES);
    }
}
