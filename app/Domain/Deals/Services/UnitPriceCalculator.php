<?php

namespace App\Domain\Deals\Services;

/**
 * Works out a comparable price per kg, litre or piece for an offer, so a
 * 400 g pack and a 250 g pack can be compared at a glance.
 */
class UnitPriceCalculator
{
    /**
     * Pack sizes like "500 g", "1,5 l", "6 x 0,33 l", "10 Stück", "4x125g".
     */
    private const QUANTITY_PATTERN = '/(?:(\d+)\s*[x×]\s*)?(\d+(?:[.,]\d+)?)\s*-?\s*(kg|g|gr|l|ltr|liter|ml|cl|stück|stk|st)(?![a-zäöüß])\.?/iu';

    /**
     * @param  array<string, mixed>  $raw  the source payload, checked for a reference price first
     * @return array{unit_price: ?float, unit_price_unit: ?string}
     */
    public function calculate(float $price, array $raw, ?string ...$texts): array
    {
        $referencePrice = data_get($raw, 'referencePrice');
        $referenceUnit = $this->baseUnit((string) (data_get($raw, 'unit.shortName') ?? data_get($raw, 'unit.name') ?? ''));

        if (is_numeric($referencePrice) && (float) $referencePrice > 0 && $referenceUnit !== null) {
            return ['unit_price' => round((float) $referencePrice, 2), 'unit_price_unit' => $referenceUnit];
        }

        if ($price <= 0) {
            return ['unit_price' => null, 'unit_price_unit' => null];
        }

        foreach ($texts as $text) {
            if ($text === null || preg_match(self::QUANTITY_PATTERN, $text, $match) !== 1) {
                continue;
            }

            $packs = $match[1] !== '' ? (int) $match[1] : 1;
            $amount = (float) str_replace(',', '.', $match[2]) * max($packs, 1);
            [$unit, $factor] = $this->conversion($match[3]);
            $quantity = $amount * $factor;

            if ($quantity > 0) {
                return ['unit_price' => round($price / $quantity, 2), 'unit_price_unit' => $unit];
            }
        }

        return ['unit_price' => null, 'unit_price_unit' => null];
    }

    /**
     * @return array{0: string, 1: float} base unit and the factor to convert into it
     */
    private function conversion(string $unit): array
    {
        return match (mb_strtolower($unit)) {
            'kg' => ['kg', 1.0],
            'g', 'gr' => ['kg', 0.001],
            'l', 'ltr', 'liter' => ['l', 1.0],
            'ml' => ['l', 0.001],
            'cl' => ['l', 0.01],
            default => ['Stück', 1.0],
        };
    }

    private function baseUnit(string $unit): ?string
    {
        return match (mb_strtolower(trim($unit))) {
            'kg', '1 kg' => 'kg',
            'l', '1 l', 'liter' => 'l',
            'stück', 'stk', 'st' => 'Stück',
            default => null,
        };
    }
}
