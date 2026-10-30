<?php

namespace App\Domain\Deals\Data;

use App\Enums\Retailer;
use Illuminate\Support\Collection;

/**
 * The headline numbers for the user's deals this week.
 */
readonly class DealSummary
{
    public function __construct(
        public int $matchCount,
        public float $possibleSaving,
        public ?Retailer $bestRetailer,
    ) {}

    /**
     * @param  Collection<int, DealRecommendation>  $recommendations
     */
    public static function fromRecommendations(Collection $recommendations): self
    {
        $bestRetailer = $recommendations
            ->groupBy(fn (DealRecommendation $recommendation): string => $recommendation->deal->retailer->value)
            ->sortByDesc(fn (Collection $group): array => [
                $group->count(),
                $group->sum(fn (DealRecommendation $recommendation): float => $recommendation->saving() ?? 0.0),
            ])
            ->keys()
            ->first();

        return new self(
            matchCount: $recommendations->count(),
            possibleSaving: round($recommendations->sum(fn (DealRecommendation $recommendation): float => $recommendation->saving() ?? 0.0), 2),
            bestRetailer: $bestRetailer !== null ? Retailer::from($bestRetailer) : null,
        );
    }
}
