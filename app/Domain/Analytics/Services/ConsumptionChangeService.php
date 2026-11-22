<?php

namespace App\Domain\Analytics\Services;

use Illuminate\Support\Collection;

/**
 * Explains how consumption spend moved between two periods, exactly and
 * without AI. For each thing bought in both, the change is split into buying
 * a different amount (at the old price) and paying a different price per unit
 * (on the new amount); the parts always add up to the whole change.
 */
class ConsumptionChangeService
{
    /**
     * @param  Collection<int, object>  $current  ConsumptionService::topItems() rows
     * @param  Collection<int, object>  $previous
     * @return array{effects: array<string, float>, price_change_percent: ?float, items: array<int, array<string, mixed>>}
     */
    public function explain(Collection $current, Collection $previous): array
    {
        $effects = ['amount' => 0.0, 'price' => 0.0, 'new' => 0.0, 'stopped' => 0.0, 'unclear' => 0.0];
        $atOldPrices = 0.0;
        $items = [];

        $now = $current->keyBy('key');
        $before = $previous->keyBy('key');

        foreach ($now->keys()->merge($before->keys())->unique() as $key) {
            $item = $now->get($key);
            $earlier = $before->get($key);
            $spend = (float) ($item?->total_spend ?? 0);
            $previousSpend = (float) ($earlier?->total_spend ?? 0);
            $change = round($spend - $previousSpend, 2);
            $row = [
                'key' => $key,
                'label' => ($item ?? $earlier)->item_name,
                'spend' => $spend,
                'previous_spend' => $previousSpend,
                'change' => $change,
                'amount_effect' => null,
                'price_effect' => null,
                'unit' => null,
                'unit_price' => null,
                'previous_unit_price' => null,
            ];

            if ($earlier === null || $item === null) {
                $row['kind'] = $earlier === null ? 'new' : 'stopped';
                $effects[$row['kind']] += $change;
                $items[] = $row;

                continue;
            }

            $basis = $this->basis($item, $earlier);

            if ($basis === null) {
                $row['kind'] = 'unclear';
                $effects['unclear'] += $change;
                $items[] = $row;

                continue;
            }

            [$unit, $amount, $previousAmount] = $basis;
            $previousPrice = $previousSpend / $previousAmount;
            $amountEffect = round(($amount - $previousAmount) * $previousPrice, 2);
            $priceEffect = round($change - $amountEffect, 2);

            $effects['amount'] += $amountEffect;
            $effects['price'] += $priceEffect;
            $atOldPrices += $amount * $previousPrice;

            $items[] = [
                ...$row,
                'kind' => abs($priceEffect) > abs($amountEffect) ? 'price' : 'amount',
                'amount_effect' => $amountEffect,
                'price_effect' => $priceEffect,
                'unit' => $unit,
                'unit_price' => round($spend / $amount, 2),
                'previous_unit_price' => round($previousPrice, 2),
            ];
        }

        return [
            'effects' => array_map(fn (float $value): float => round($value, 2), $effects),
            'price_change_percent' => $atOldPrices > 0 ? round($effects['price'] / $atOldPrices * 100, 1) : null,
            'items' => collect($items)->sortByDesc(fn (array $row): float => abs($row['change']))->values()->all(),
        ];
    }

    /**
     * What both periods can be compared per: the base unit when every purchase
     * has a known size, or the item when it is the same single product in both.
     * Null when neither holds, rather than guessing.
     *
     * @return array{0: string, 1: float, 2: float}|null
     */
    private function basis(object $item, object $earlier): ?array
    {
        $isMeasured = fn (object $row): bool => $row->amount > 0 && $row->unmeasured_count === 0;

        if ($isMeasured($item) && $isMeasured($earlier) && $item->unit === $earlier->unit) {
            return [$item->unit, $item->amount, $earlier->amount];
        }

        $isSameProduct = count($item->variants) === 1 && $item->variants === $earlier->variants;

        return $isSameProduct && $item->total_quantity > 0 && $earlier->total_quantity > 0
            ? ['item', $item->total_quantity, $earlier->total_quantity]
            : null;
    }
}
