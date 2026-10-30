<?php

namespace App\Domain\Deals\Data;

use App\Domain\Products\Data\PurchasedProduct;
use App\Models\Deal;
use Illuminate\Support\Collection;

/**
 * One product the user buys, on offer at several retailers: the cheapest offer per
 * retailer, cheapest first. Offers are compared by unit price when they all share
 * a unit (pack sizes differ), otherwise by pack price.
 */
readonly class DealComparison
{
    /**
     * @param  Collection<int, Deal>  $deals  one per retailer, cheapest first
     */
    private function __construct(
        public PurchasedProduct $product,
        public Collection $deals,
        public bool $comparesUnitPrices,
    ) {}

    /**
     * @param  Collection<int, Deal>  $deals  every matching offer
     */
    public static function of(PurchasedProduct $product, Collection $deals): self
    {
        $comparesUnitPrices = self::shareUnit($deals);
        $value = fn (Deal $deal): float => (float) ($comparesUnitPrices ? $deal->unit_price : $deal->price);

        $cheapestPerRetailer = $deals
            ->groupBy(fn (Deal $deal): string => $deal->retailer->value)
            ->map(fn (Collection $offers): Deal => $offers->sortBy($value)->first())
            ->sortBy($value)
            ->values();

        return new self($product, $cheapestPerRetailer, $comparesUnitPrices);
    }

    public function retailerCount(): int
    {
        return $this->deals->count();
    }

    /**
     * How much more the dearest retailer charges than the cheapest, in the compared price.
     */
    public function priceGap(): float
    {
        $value = fn (Deal $deal): float => (float) ($this->comparesUnitPrices ? $deal->unit_price : $deal->price);

        return round($value($this->deals->last()) - $value($this->deals->first()), 2);
    }

    /**
     * @param  Collection<int, Deal>  $deals
     */
    private static function shareUnit(Collection $deals): bool
    {
        return $deals->every(fn (Deal $deal): bool => $deal->unit_price !== null)
            && $deals->pluck('unit_price_unit')->unique()->count() === 1;
    }
}
