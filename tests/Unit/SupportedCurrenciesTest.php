<?php

namespace Tests\Unit;

use App\Domain\Shared\Support\SupportedCurrencies;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportedCurrenciesTest extends TestCase
{
    /**
     * Each currency renders in its own country's pattern, matching Intl in the browser.
     *
     * @return array<string, array{0: ?string, 1: float, 2: string}>
     */
    public static function amounts(): array
    {
        return [
            'euro, German grouping' => ['EUR', 1234567.891, "1.234.567,89\u{a0}€"],
            'US dollar' => ['USD', 1234567.891, '$1,234,567.89'],
            'pound' => ['GBP', -42.5, '-£42.50'],
            'rupee, lakh grouping' => ['INR', 1234567.891, '₹12,34,567.89'],
            'Pakistani rupee' => ['PKR', 1234.5, "Rs\u{a0}1,234.50"],
            'lira' => ['TRY', 1234.5, '₺1.234,50'],
            'missing currency falls back to euro' => [null, 0, "0,00\u{a0}€"],
        ];
    }

    #[DataProvider('amounts')]
    public function test_amounts_use_the_currencys_country_pattern(?string $currency, float $amount, string $expected): void
    {
        $this->assertSame($expected, SupportedCurrencies::format($amount, $currency));
    }
}
