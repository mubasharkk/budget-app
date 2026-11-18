<?php

namespace App\Domain\Analytics\Data;

/**
 * What counts as consumption: personal receipts only unless business ones are
 * included, minus the categories the user has excluded (a parent category
 * excludes its subcategories too).
 */
final readonly class ConsumptionScope
{
    /**
     * @param  array<int, int>  $excludedCategoryIds
     */
    public function __construct(
        public bool $includeBusiness = false,
        public array $excludedCategoryIds = [],
    ) {}

    public static function default(): self
    {
        return new self;
    }
}
