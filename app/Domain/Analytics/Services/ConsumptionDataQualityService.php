<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumedLine;
use App\Domain\Products\Support\ProductQuantity;
use Illuminate\Support\Collection;

/**
 * What keeps consumption figures from being exact, most money first: lines
 * without a product, products without a general name, without a pack size, or
 * measured in another unit than their general name.
 */
class ConsumptionDataQualityService
{
    public const SHOWN = 6;

    /**
     * @param  Collection<int, ConsumedLine>  $lines
     * @return array{count: int, affected_percent: int, rows: array<int, array<string, mixed>>}
     */
    public function gaps(Collection $lines): array
    {
        $bought = $lines->filter(fn (ConsumedLine $line): bool => $line->total > 0);
        $spend = $bought->sum(fn (ConsumedLine $line): float => $line->total);

        $gaps = $bought
            ->groupBy(fn (ConsumedLine $line): string => $line->productId !== null ? 'product:'.$line->productId : 'name:'.mb_strtolower($line->name))
            ->map(function (Collection $purchases, string $key): ?array {
                /** @var ConsumedLine $line */
                $line = $purchases->first();
                $missing = $this->missing($line);

                return $missing === [] ? null : [
                    'key' => $key,
                    'product_id' => $line->productId,
                    'name' => $line->name,
                    'label' => $line->productName ?? $line->name,
                    'group_name' => $line->groupName,
                    'group_unit' => $line->groupUnit,
                    'missing' => $missing,
                    'purchases' => $purchases->count(),
                    'spend' => round($purchases->sum(fn (ConsumedLine $purchase): float => $purchase->total), 2),
                ];
            })
            ->filter()
            ->sortByDesc('spend')
            ->values();

        return [
            'count' => $gaps->count(),
            'affected_percent' => $spend > 0 ? (int) round($gaps->sum('spend') / $spend * 100) : 0,
            'rows' => $gaps->take(self::SHOWN)->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function missing(ConsumedLine $line): array
    {
        if ($line->productId === null) {
            return ['name', 'size'];
        }

        $perItem = ProductQuantity::perUnit($line->productUnit, $line->productSize);
        $groupUnit = ProductQuantity::baseUnit($line->groupUnit);

        return array_values(array_filter([
            $line->groupId === null ? 'name' : null,
            $perItem === null ? 'size' : null,
            $perItem !== null && $groupUnit !== null && $groupUnit !== $perItem['unit'] ? 'unit' : null,
        ]));
    }
}
