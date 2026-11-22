<?php

namespace App\Domain\Products\Support;

/**
 * Converts what was bought into an amount in a base unit (l, kg or pcs), from
 * a product's unit and pack size: 6 × "1 l" milk is 6 l, 2 × "500 g" is 1 kg.
 * Returns null when the amount cannot be known rather than guessing.
 */
final class ProductQuantity
{
    public const LITRES = 'l';

    public const KILOGRAMS = 'kg';

    public const PIECES = 'pcs';

    /**
     * Unit spellings mapped to [base unit, factor to the base unit].
     *
     * @var array<string, array{0: string, 1: float}>
     */
    private const UNITS = [
        'l' => [self::LITRES, 1.0],
        'lt' => [self::LITRES, 1.0],
        'ltr' => [self::LITRES, 1.0],
        'liter' => [self::LITRES, 1.0],
        'litre' => [self::LITRES, 1.0],
        'liters' => [self::LITRES, 1.0],
        'litres' => [self::LITRES, 1.0],
        'ml' => [self::LITRES, 0.001],
        'cl' => [self::LITRES, 0.01],
        'kg' => [self::KILOGRAMS, 1.0],
        'kilo' => [self::KILOGRAMS, 1.0],
        'g' => [self::KILOGRAMS, 0.001],
        'gr' => [self::KILOGRAMS, 0.001],
        'gram' => [self::KILOGRAMS, 0.001],
        'grams' => [self::KILOGRAMS, 0.001],
        'pcs' => [self::PIECES, 1.0],
        'pc' => [self::PIECES, 1.0],
        'piece' => [self::PIECES, 1.0],
        'pieces' => [self::PIECES, 1.0],
        'stk' => [self::PIECES, 1.0],
        'st' => [self::PIECES, 1.0],
        'stück' => [self::PIECES, 1.0],
        'x' => [self::PIECES, 1.0],
    ];

    /**
     * The base unit (l, kg or pcs) for a unit spelling, or null if unknown.
     */
    public static function baseUnit(?string $unit): ?string
    {
        return self::UNITS[self::clean($unit)][0] ?? null;
    }

    /**
     * How much of the base unit one bought unit of the product holds, e.g.
     * 0.5 for a "500 g" pack. Null when the pack size is unknown.
     *
     * @return array{amount: float, unit: string}|null
     */
    public static function perUnit(?string $unit, ?string $size): ?array
    {
        $parsedSize = self::parseSize($size);

        if ($parsedSize !== null) {
            [$amount, $sizeUnit] = $parsedSize;
            $known = self::UNITS[$sizeUnit ?? self::clean($unit)] ?? null;

            return $known === null ? null : ['amount' => $amount * $known[1], 'unit' => $known[0]];
        }

        $known = self::UNITS[self::clean($unit)] ?? null;

        if ($known === null) {
            return null;
        }

        // Without a pack size, kg items are weighed (the quantity is the weight)
        // and pieces are counted; a litre product of unknown size is unknown.
        return match ($known[0]) {
            self::KILOGRAMS, self::PIECES => ['amount' => $known[1], 'unit' => $known[0]],
            default => null,
        };
    }

    /**
     * Total amount in the base unit for a bought quantity.
     *
     * @return array{amount: float, unit: string}|null
     */
    public static function total(?string $unit, ?string $size, float $quantity): ?array
    {
        $perUnit = self::perUnit($unit, $size);

        return $perUnit === null ? null : ['amount' => $perUnit['amount'] * $quantity, 'unit' => $perUnit['unit']];
    }

    /**
     * "3.5 l", "1.23 kg", "12 pcs".
     */
    public static function label(float $amount, ?string $unit): string
    {
        $number = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

        return trim($number.' '.$unit);
    }

    /**
     * "500g" → [500, 'g'], "1,5" → [1.5, null], "6x0.33l" → [1.98, 'l'].
     *
     * @return array{0: float, 1: ?string}|null
     */
    private static function parseSize(?string $size): ?array
    {
        if ($size === null || ! preg_match('/^\s*(?:(\d+)\s*[x×*]\s*)?(\d+(?:[.,]\d+)?)\s*([\p{L}]*)\.?\s*$/u', $size, $matches)) {
            return null;
        }

        $amount = (float) str_replace(',', '.', $matches[2]) * (($matches[1] ?? '') !== '' ? (int) $matches[1] : 1);
        $unit = self::clean($matches[3] ?? '');

        if ($amount <= 0 || ($unit !== '' && ! isset(self::UNITS[$unit]))) {
            return null;
        }

        return [$amount, $unit === '' ? null : $unit];
    }

    private static function clean(?string $unit): string
    {
        return mb_strtolower(trim((string) $unit, " \t."));
    }
}
