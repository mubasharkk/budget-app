<?php

namespace App\Domain\Deals\Data;

use App\Enums\Retailer;

/**
 * One supermarket offer as delivered by a deal source, before it is stored.
 */
readonly class Offer
{
    /**
     * @param  array<string, mixed>  $raw  the source's original payload for this offer
     */
    public function __construct(
        public string $externalId,
        public Retailer $retailer,
        public ?string $advertiserName,
        public string $title,
        public ?string $brand,
        public ?string $description,
        public float $price,
        public ?float $oldPrice,
        public ?string $unit,
        public ?string $validFrom,
        public ?string $validTo,
        public array $raw = [],
        public ?string $externalUrl = null,
        public ?string $sourceUrl = null,
    ) {}

    public function discountPercent(): ?int
    {
        if ($this->oldPrice === null || $this->oldPrice <= $this->price) {
            return null;
        }

        return (int) round((1 - $this->price / $this->oldPrice) * 100);
    }
}
