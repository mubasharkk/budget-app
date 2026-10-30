<?php

namespace Tests\Unit;

use App\Domain\Deals\Services\UnitPriceCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UnitPriceCalculatorTest extends TestCase
{
    /**
     * @return array<string, array{0: float, 1: string, 2: ?float, 3: ?string}>
     */
    public static function packSizes(): array
    {
        return [
            'grams' => [2.00, 'Gouda jung 400 g', 5.00, 'kg'],
            'grams without space' => [2.49, 'Joghurt 500g Becher', 4.98, 'kg'],
            'kilograms with comma' => [3.00, 'Kartoffeln 2,5 kg', 1.20, 'kg'],
            'litres' => [0.95, 'Vollmilch 3,5% 1 l', 0.95, 'l'],
            'millilitres' => [1.49, 'Duschgel 250 ml', 5.96, 'l'],
            'centilitres' => [7.99, 'Gin 70 cl', 11.41, 'l'],
            'multipack' => [4.99, 'Mineralwasser 6 x 1,5 l', 0.55, 'l'],
            'multipack grams' => [1.99, 'Joghurt 4x125g', 3.98, 'kg'],
            'pieces' => [1.79, 'Bio Eier 10 Stück', 0.18, 'Stück'],
            'pieces abbreviated' => [2.99, 'Brötchen 6 St.', 0.50, 'Stück'],
            'no pack size' => [1.99, 'Bananen lose', null, null],
            'unit word inside another word' => [1.99, 'Gartenstuhl 1 Glas', null, null],
        ];
    }

    #[DataProvider('packSizes')]
    public function test_unit_price_is_derived_from_the_pack_size(float $price, string $title, ?float $unitPrice, ?string $unit): void
    {
        $result = (new UnitPriceCalculator)->calculate($price, [], $title);

        $this->assertSame(['unit_price' => $unitPrice, 'unit_price_unit' => $unit], $result);
    }

    public function test_the_sources_reference_price_wins_over_parsing(): void
    {
        $result = (new UnitPriceCalculator)->calculate(4.99, ['referencePrice' => 9.98, 'unit' => ['shortName' => 'kg']], 'Kaffee 500 g');

        $this->assertSame(['unit_price' => 9.98, 'unit_price_unit' => 'kg'], $result);
    }

    public function test_later_texts_are_used_when_the_title_has_no_pack_size(): void
    {
        $result = (new UnitPriceCalculator)->calculate(2.00, [], 'Kaffee', null, 'je 250 g');

        $this->assertSame(['unit_price' => 8.00, 'unit_price_unit' => 'kg'], $result);
    }

    public function test_a_zero_price_has_no_unit_price(): void
    {
        $this->assertSame(['unit_price' => null, 'unit_price_unit' => null], (new UnitPriceCalculator)->calculate(0.0, [], 'Milch 1 l'));
    }
}
