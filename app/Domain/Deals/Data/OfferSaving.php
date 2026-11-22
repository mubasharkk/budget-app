<?php

namespace App\Domain\Deals\Data;

/**
 * What an offer saves on the user's usual amount, and the prices per unit behind it.
 */
readonly class OfferSaving
{
    public function __construct(
        public float $saving,
        public float $usualPrice,
        public float $offerPrice,
        public string $unit,
        public string $amountLabel,
    ) {}

    /**
     * @param  array<string, mixed>  $row  from ConsumptionSavingsService::findOffers()
     */
    public static function fromArray(array $row): self
    {
        return new self($row['saving'], $row['usual_price'], $row['price'], $row['unit'], $row['amount_label']);
    }
}
