<?php

namespace App\Domain\Deals\Data;

use App\Enums\DealSort;
use App\Enums\DealWeek;
use App\Enums\Retailer;

/**
 * How the user wants to browse the stored offers.
 */
readonly class DealFilters
{
    public const DEFAULT_PER_GROUP = 6;

    /**
     * Category id used for offers that have no category.
     */
    public const UNCATEGORIZED = 0;

    public function __construct(
        public ?string $searchTerm = null,
        public ?Retailer $retailer = null,
        public DealSort $sort = DealSort::Saving,
        public DealWeek $week = DealWeek::Current,
        public bool $onlyMyCategories = false,
        public ?int $expandedCategoryId = null,
        public int $perGroup = self::DEFAULT_PER_GROUP,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request input
     */
    public static function fromArray(array $input): self
    {
        $searchTerm = trim((string) ($input['q'] ?? ''));

        return new self(
            searchTerm: $searchTerm === '' ? null : $searchTerm,
            retailer: Retailer::tryFrom((string) ($input['retailer'] ?? '')),
            sort: DealSort::tryFrom((string) ($input['sort'] ?? '')) ?? DealSort::Saving,
            week: DealWeek::tryFrom((string) ($input['week'] ?? '')) ?? DealWeek::Current,
            onlyMyCategories: filter_var($input['mine'] ?? false, FILTER_VALIDATE_BOOLEAN),
            expandedCategoryId: isset($input['expand']) ? (int) $input['expand'] : null,
            perGroup: (int) ($input['per_group'] ?? self::DEFAULT_PER_GROUP),
        );
    }

    /**
     * The expanded category lists every offer instead of the first few.
     */
    public function isExpanded(int $categoryId): bool
    {
        return $this->expandedCategoryId === $categoryId;
    }

    /**
     * The filters as URL query values, for the page's filter controls.
     *
     * @return array{q: string, retailer: string, sort: string, week: string, mine: bool, expand: ?int}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->searchTerm ?? '',
            'retailer' => $this->retailer?->value ?? '',
            'sort' => $this->sort->value,
            'week' => $this->week->value,
            'mine' => $this->onlyMyCategories,
            'expand' => $this->expandedCategoryId,
        ];
    }
}
