<?php

namespace App\Domain\Analytics\Data;

/**
 * What counts as consumption: personal receipts only unless business ones are
 * included, minus the categories the user has excluded (a parent category
 * excludes its subcategories too), in the user's currency. There are no
 * exchange rates, so amounts in other currencies are never added in.
 */
final readonly class ConsumptionScope
{
    /**
     * @param  array<int, int>  $excludedCategoryIds
     * @param  string|null  $currency  null counts every currency
     */
    public function __construct(
        public bool $includeBusiness = false,
        public array $excludedCategoryIds = [],
        public ?string $currency = null,
    ) {}

    public function inAnyCurrency(): self
    {
        return new self($this->includeBusiness, $this->excludedCategoryIds);
    }

    public static function default(): self
    {
        return new self;
    }
}
