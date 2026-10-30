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
    ) {}

    /**
     * Saving per pack compared with the last price paid; null when the offer isn't cheaper.
     */
    public function saving(): ?float
    {
        $saving = round($this->product->lastPaidPrice - (float) $this->deal->price, 2);

        return $saving > 0 ? $saving : null;
    }
}
