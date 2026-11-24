<?php

namespace App\Domain\Deals\Data;

use App\Domain\Products\Data\PurchasedProduct;
use App\Domain\Products\Support\ProductQuantity;
use App\Models\Deal;
use Illuminate\Support\Collection;

/**
 * One product the user buys, on offer at several retailers: the cheapest offer per
 * retailer, cheapest first. Offers are only ever compared by price per kg, litre or
 * piece, never by pack price, since packs differ in size. Offers without a unit
 * price are left out; when offers come in different units, the unit most retailers
 * share is compared.
 */
readonly class DealComparison
{
    /**
     * @param  Collection<int, Deal>  $deals  one per retailer, cheapest per unit first
     */
    private function __construct(
        public PurchasedProduct $product,
        public Collection $deals,
    ) {}

    /**
     * @param  Collection<int, Deal>  $deals  every matching offer
     */
    public static function of(PurchasedProduct $product, Collection $deals): self
    {
        $unitPrice = fn (Deal $deal): float => (float) $deal->unit_price;

        $cheapestPerRetailer = $deals
            ->filter(fn (Deal $deal): bool => $unitPrice($deal) > 0 && ProductQuantity::baseUnit($deal->unit_price_unit) !== null)
            ->groupBy(fn (Deal $deal): string => ProductQuantity::baseUnit($deal->unit_price_unit))
            ->map(fn (Collection $offers): Collection => $offers
                ->groupBy(fn (Deal $deal): string => $deal->retailer->value)
                ->map(fn (Collection $retailerOffers): Deal => $retailerOffers->sortBy($unitPrice)->first())
                ->sortBy($unitPrice)
                ->values())
            ->sortByDesc(fn (Collection $offers): int => $offers->count())
            ->first() ?? collect();

        return new self($product, $cheapestPerRetailer);
    }

    public function retailerCount(): int
    {
        return $this->deals->count();
    }

    /**
     * How much more per unit the dearest retailer charges than the cheapest.
     */
    public function priceGap(): float
    {
        if ($this->deals->isEmpty()) {
            return 0.0;
        }

        return round((float) $this->deals->last()->unit_price - (float) $this->deals->first()->unit_price, 2);
    }
}
