<?php

namespace App\Domain\Products\Data;

/**
 * A product the user has bought recently, with what they last paid for it.
 */
readonly class PurchasedProduct
{
    public function __construct(
        public int $productId,
        public string $name,
        public string $normalizedName,
        public ?int $categoryId,
        public int $purchaseCount,
        public float $lastPaidPrice,
        public ?string $lastVendor,
        public string $lastPurchasedAt,
    ) {}
}
