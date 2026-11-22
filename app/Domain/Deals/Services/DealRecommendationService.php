<?php

namespace App\Domain\Deals\Services;

use App\Domain\Analytics\Data\ConsumedLine;
use App\Domain\Analytics\Services\ConsumptionSavingsService;
use App\Domain\Analytics\Services\ConsumptionService;
use App\Domain\Deals\Data\DealRecommendation;
use App\Domain\Deals\Data\OfferSaving;
use App\Domain\Identity\Services\UserSettingsService;
use App\Domain\Products\Data\PurchasedProduct;
use App\Domain\Products\Services\PurchaseHistoryService;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "For you": current offers on the products the user actually buys. A saving
 * is only claimed where Insights would claim it (ConsumptionSavingsService);
 * other offers on those products are still listed, without one.
 */
class DealRecommendationService
{
    public function __construct(
        private DealCatalogService $dealCatalogService,
        private PurchaseHistoryService $purchaseHistoryService,
        private ProductDealMatcher $productDealMatcher,
        private ConsumptionService $consumptionService,
        private ConsumptionSavingsService $savingsService,
        private UserSettingsService $userSettingsService,
    ) {}

    /**
     * One offer per purchased product (the one that saves most, else the cheapest),
     * ranked by saving, then by how often the product is bought.
     *
     * @return Collection<int, DealRecommendation>
     */
    public function forUser(User $user, string $postalCode): Collection
    {
        $products = $this->purchaseHistoryService->recentProducts($user->id);
        $lines = $this->comparableLines($user);

        if ($products->isEmpty() && $lines->isEmpty()) {
            return collect();
        }

        $deals = $this->dealCatalogService->currentDeals($postalCode);
        $saving = $this->withSaving($products, $lines, $deals);
        $savingProductIds = $saving->map(fn (DealRecommendation $recommendation): int => $recommendation->product->productId)->flip();
        $usedDealIds = $saving->mapWithKeys(fn (DealRecommendation $recommendation): array => [$recommendation->deal->id => true])->all();

        $others = $products
            ->reject(fn (PurchasedProduct $product): bool => $savingProductIds->has($product->productId))
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
            ->filter();

        return $saving->concat($others)
            ->sortBy(fn (DealRecommendation $recommendation): array => [
                -($recommendation->saving() ?? 0.0),
                -$recommendation->product->purchaseCount,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, PurchasedProduct>  $products
     * @param  Collection<int, ConsumedLine>  $lines
     * @param  Collection<int, Deal>  $deals
     * @return Collection<int, DealRecommendation>
     */
    private function withSaving(Collection $products, Collection $lines, Collection $deals): Collection
    {
        $productsById = $products->keyBy('productId');
        $dealsById = $deals->keyBy('id');

        return collect($this->savingsService->findOffers($lines, $deals))
            ->map(fn (array $row): DealRecommendation => new DealRecommendation(
                deal: $dealsById->get($row['offer']['id']),
                product: $productsById->get($row['product_id']) ?? $this->productFromLines($lines->where('productId', $row['product_id'])),
                offerSaving: OfferSaving::fromArray($row),
            ));
    }

    /**
     * The purchases an offer can be compared with: none for users not counting in euros.
     *
     * @return Collection<int, ConsumedLine>
     */
    private function comparableLines(User $user): Collection
    {
        $scope = $this->userSettingsService->consumptionScope($user);

        return $scope->currency === ConsumptionSavingsService::OFFERS_CURRENCY
            ? $this->consumptionService->recentLines($user->id, ConsumptionSavingsService::PRICE_DAYS, $scope)
            : collect();
    }

    /**
     * Products the user named on Insights have purchases but no price observations.
     *
     * @param  Collection<int, ConsumedLine>  $purchases  of one product, oldest first
     */
    private function productFromLines(Collection $purchases): PurchasedProduct
    {
        $latest = $purchases->last();

        return new PurchasedProduct(
            productId: $latest->productId,
            name: $latest->productName,
            normalizedName: $latest->productName,
            categoryId: $latest->categoryId,
            purchaseCount: $purchases->count(),
            lastPaidPrice: round($latest->quantity > 0 ? $latest->total / $latest->quantity : $latest->total, 2),
            lastVendor: $latest->vendor,
            lastPurchasedAt: $latest->date->toDateString(),
        );
    }
}
