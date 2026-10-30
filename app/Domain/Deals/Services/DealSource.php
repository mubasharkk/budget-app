<?php

namespace App\Domain\Deals\Services;

use App\Domain\Deals\Data\Offer;
use App\Domain\Deals\Exceptions\DealSourceUnavailable;

interface DealSource
{
    /**
     * Identifier stored in deals.source, e.g. "marktguru".
     */
    public function name(): string;

    /**
     * One page of current offers near a postal code matching a search term,
     * limited to supported retailers.
     *
     * @return array{offers: array<int, Offer>, has_more: bool}
     *
     * @throws DealSourceUnavailable
     */
    public function search(string $postalCode, string $searchTerm, int $limit, int $offset = 0): array;
}
