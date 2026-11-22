<?php

namespace App\Domain\Analytics\Data;

use App\Domain\Products\Support\ProductQuantity;
use Carbon\CarbonImmutable;

/**
 * One consumed receipt line with what is known about its product.
 */
readonly class ConsumedLine
{
    /**
     * @param  string  $key  what it counts as, like ConsumptionService::topItems(): group, else product, else receipt name
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $name,
        public CarbonImmutable $date,
        public ?string $vendor,
        public float $quantity,
        public float $total,
        public ?int $categoryId,
        public ?int $productId,
        public ?string $productName,
        public ?string $productUnit,
        public ?string $productSize,
        public ?int $groupId,
        public ?string $groupName,
        public ?string $groupUnit,
    ) {}

    /**
     * @return array{amount: float, unit: string}|null
     */
    public function amount(): ?array
    {
        return $this->productId !== null
            ? ProductQuantity::total($this->productUnit, $this->productSize, $this->quantity)
            : null;
    }

    public function hasPackSize(): bool
    {
        return filled($this->productSize) && ProductQuantity::perUnit($this->productUnit, $this->productSize) !== null;
    }

    /**
     * Sold by weight: kilos without a pack size, so the quantity is the weight.
     */
    public function isWeighed(): bool
    {
        return blank($this->productSize) && ProductQuantity::baseUnit($this->productUnit) === ProductQuantity::KILOGRAMS;
    }
}
