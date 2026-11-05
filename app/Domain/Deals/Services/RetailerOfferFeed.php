<?php

namespace App\Domain\Deals\Services;

use App\Domain\Deals\Data\Offer;
use App\Domain\Deals\Exceptions\DealSourceUnavailable;
use App\Enums\Retailer;

/**
 * A retailer's own offer catalogue, the same for every store it serves. The
 * sync uses it only for postal codes where the main DealSource has no offers
 * from that retailer (e.g. Aldi Nord, which marktguru does not carry).
 */
interface RetailerOfferFeed
{
    /**
     * Identifier stored in deals.source, e.g. "aldi_nord".
     */
    public function name(): string;

    public function retailer(): Retailer;

    /**
     * Every offer of this week and next week.
     *
     * @return array<int, Offer>
     *
     * @throws DealSourceUnavailable
     */
    public function offers(): array;
}
