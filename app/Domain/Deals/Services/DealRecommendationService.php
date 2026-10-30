<?php

namespace App\Domain\Deals\Services;

use App\Domain\Deals\Data\DealRecommendation;
use App\Domain\Products\Data\PurchasedProduct;
use App\Domain\Products\Services\PurchaseHistoryService;
use App\Models\Deal;
use Illuminate\Support\Collection;

/**
 * "For you": current offers on the products the user actually buys.
 */
class DealRecommendationService
{
    public function __construct(
        private DealCatalogService $dealCatalogService,
        private PurchaseHistoryService $purchaseHistoryService,
        private ProductDealMatcher $productDealMatcher,
    ) {}

    /**
     * Best offer per purchased product, ranked by saving, then by how often the product is bought.
     *
     * @return Collection<int, DealRecommendation>
     */
    public function forUser(int $userId, string $postalCode): Collection
    {
        $products = $this->purchaseHistoryService->recentProducts($userId);

        if ($products->isEmpty()) {
            return collect();
        }

        $deals = $this->dealCatalogService->currentDeals($postalCode);
        $usedDealIds = [];

        return $products
            ->map(function (PurchasedProduct $product) use ($deals, &$usedDealIds): ?DealRecommendation {
                $deal = $this->productDealMatcher
                    ->matching($product, $deals->reject(fn (Deal $deal): bool => isset($usedDealIds[$deal->id])))
                    ->sortBy(fn (Deal $deal): float => (float) $deal->price)
                    ->first();

                if ($deal === null) {
                    return null;
                }

                $usedDealIds[$deal->id] = true;

                return new DealRecommendation($deal, $product);
            })
            ->filter()
            ->sortBy(fn (DealRecommendation $recommendation): array => [
                -($recommendation->saving() ?? 0.0),
                -$recommendation->product->purchaseCount,
            ])
            ->values();
    }
}
