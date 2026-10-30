<?php

namespace App\Domain\Deals\Data;

use App\Models\Deal;
use Illuminate\Support\Collection;

/**
 * Offers of one category, possibly truncated for the overview.
 */
readonly class DealGroup
{
    /**
     * @param  Collection<int, Deal>  $deals
     */
    public function __construct(
        public int $categoryId,
        public string $name,
        public int $total,
        public Collection $deals,
    ) {}
}
