<?php

namespace App\Domain\Deals\Services;

use App\Domain\Deals\Data\DealFilters;
use App\Domain\Deals\Data\DealGroup;
use App\Domain\Products\Services\PurchaseHistoryService;
use App\Enums\DealSort;
use App\Enums\DealWeek;
use App\Models\Deal;
use App\Models\DealPostalCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Browsing the stored offers for a postal code.
 */
class DealCatalogService
{
    public function __construct(private PurchaseHistoryService $purchaseHistoryService) {}

    /**
     * Matching offers grouped by category: largest group first, "Other offers" last among equals.
     *
     * @return Collection<int, DealGroup>
     */
    public function browse(int $userId, string $postalCode, DealFilters $filters): Collection
    {
        $query = $this->filtered($postalCode, $filters);

        if ($filters->onlyMyCategories) {
            $query->whereIn('category_id', $this->purchaseHistoryService->purchasedCategoryIds($userId));
        }

        return $query->get()
            ->groupBy(fn (Deal $deal): int => $deal->category_id ?? DealFilters::UNCATEGORIZED)
            ->map(fn (Collection $deals, int $categoryId): DealGroup => new DealGroup(
                categoryId: $categoryId,
                name: $deals->first()->category?->name ?? 'Other offers',
                total: $deals->count(),
                deals: $filters->isExpanded($categoryId) ? $deals->values() : $deals->take($filters->perGroup)->values(),
            ))
            ->sortBy(fn (DealGroup $group): array => [
                -$group->total,
                $group->categoryId === DealFilters::UNCATEGORIZED ? 1 : 0,
                $group->name,
            ])
            ->values();
    }

    /**
     * Offers valid today for the postal code, used for personal matching.
     *
     * @return Collection<int, Deal>
     */
    public function currentDeals(string $postalCode): Collection
    {
        return Deal::query()->with('category')->availableIn($postalCode)->current()->get();
    }

    public function lastSyncedAt(string $postalCode): ?string
    {
        $syncedAt = DealPostalCode::query()->where('postal_code', $postalCode)->max('fetched_at');

        return $syncedAt !== null ? Carbon::parse($syncedAt)->toIso8601String() : null;
    }

    /**
     * @return Builder<Deal>
     */
    private function filtered(string $postalCode, DealFilters $filters): Builder
    {
        $query = Deal::query()->with('category')->availableIn($postalCode);

        $filters->week === DealWeek::Upcoming ? $query->upcoming() : $query->current();

        $query->when($filters->retailer !== null, fn (Builder $q) => $q->where('retailer', $filters->retailer->value));

        if ($filters->searchTerm !== null) {
            $like = '%'.addcslashes(mb_strtolower($filters->searchTerm), '%_\\').'%';

            $query->where(fn (Builder $q) => $q
                ->whereRaw('LOWER(title) LIKE ?', [$like])
                ->orWhereRaw('LOWER(brand) LIKE ?', [$like])
                ->orWhereRaw('LOWER(description) LIKE ?', [$like]));
        }

        return $this->sorted($query, $filters->sort);
    }

    /**
     * @param  Builder<Deal>  $query
     * @return Builder<Deal>
     */
    private function sorted(Builder $query, DealSort $sort): Builder
    {
        return match ($sort) {
            DealSort::Saving => $query->orderByRaw('discount_percent IS NULL')->orderByDesc('discount_percent')->orderBy('price'),
            DealSort::Price => $query->orderBy('price'),
            DealSort::UnitPrice => $query->orderByRaw('unit_price IS NULL')->orderBy('unit_price')->orderBy('price'),
            DealSort::Ending => $query->orderByRaw('valid_to IS NULL')->orderBy('valid_to')->orderBy('price'),
        };
    }
}
