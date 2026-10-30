<?php

namespace App\Domain\Deals\Services;

use App\Domain\Deals\Data\DealComparison;
use App\Domain\Products\Data\PurchasedProduct;
use App\Domain\Products\Services\PurchaseHistoryService;
use Illuminate\Support\Collection;

/**
 * "Where is it cheapest?": products the user buys that several retailers have on offer.
 */
class DealComparisonService
{
    public const DEFAULT_LIMIT = 6;

    private const MIN_RETAILERS = 2;

    public function __construct(
        private DealCatalogService $dealCatalogService,
        private PurchaseHistoryService $purchaseHistoryService,
        private ProductDealMatcher $productDealMatcher,
    ) {}

    /**
     * Comparisons with the biggest price gap first, then the most often bought product.
     *
     * @return Collection<int, DealComparison>
     */
    public function forUser(int $userId, string $postalCode, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $products = $this->purchaseHistoryService->recentProducts($userId);

        if ($products->isEmpty()) {
            return collect();
        }

        $deals = $this->dealCatalogService->currentDeals($postalCode);

        return $products
            ->map(fn (PurchasedProduct $product): DealComparison => DealComparison::of(
                $product,
                $this->productDealMatcher->matching($product, $deals),
            ))
            ->filter(fn (DealComparison $comparison): bool => $comparison->retailerCount() >= self::MIN_RETAILERS)
            ->sortBy(fn (DealComparison $comparison): array => [
                -$comparison->priceGap(),
                -$comparison->product->purchaseCount,
            ])
            ->take($limit)
            ->values();
    }
}
