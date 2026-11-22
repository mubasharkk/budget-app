<?php

namespace App\Domain\Deals\Data;

use App\Domain\Products\Data\PurchasedProduct;
use App\Models\Deal;

/**
 * A current offer on something the user buys, with what they'd save.
 */
readonly class DealRecommendation
{
    public function __construct(
        public Deal $deal,
        public PurchasedProduct $product,
        public ?OfferSaving $offerSaving = null,
    ) {}

    /**
     * Null unless the offer compares like for like, never a difference of pack prices.
     */
    public function saving(): ?float
    {
        return $this->offerSaving?->saving;
    }
}
