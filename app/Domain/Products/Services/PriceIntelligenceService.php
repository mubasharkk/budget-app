<?php

namespace App\Domain\Products\Services;

use App\Domain\Analytics\Data\ConsumptionScope;
use App\Domain\Analytics\Services\ConsumptionService;
use App\Domain\Identity\Services\UserSettingsService;
use App\Enums\LineItemType;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What the user paid for their products and where it was cheapest. Prices only
 * ever come from product lines on receipts that count (not duplicates, deposits,
 * fees or lines the user left out). The overviews follow what counts as
 * consumption (ConsumptionScope); a single product's page shows all of its
 * purchases. Either way only prices in the user's currency are compared, since
 * there are no exchange rates.
 */
class PriceIntelligenceService
{
    /**
     * @var array<int, ConsumptionScope>
     */
    private array $scopes = [];

    public function __construct(
        private ConsumptionService $consumptionService,
        private UserSettingsService $userSettingsService,
    ) {}

    /**
     * Ranked savings opportunities where the user paid above their observed minimum price.
     *
     * @return Collection<int, object>
     */
    public function savingsOpportunities(
        int $userId,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 20,
    ): Collection {
        $scope = $this->scopeFor($userId);
        $cheapestVendors = $this->cheapestVendorByProduct($userId, $scope);
        $minPrices = $this->minPriceSubquery($userId, $scope);

        $query = ReceiptItem::query()
            ->join('receipts', fn ($join) => $join->on('receipt_items.receipt_id', '=', 'receipts.id')->whereNull('receipts.duplicate_of_id'))
            ->join('products', 'receipt_items.product_id', '=', 'products.id')
            ->joinSub($minPrices, 'min_prices', function ($join): void {
                $join->on('products.id', '=', 'min_prices.product_id');
            })
            ->where('products.user_id', $userId)
            ->where('receipts.user_id', $userId)
            ->where('receipt_items.line_type', LineItemType::Product->value)
            ->whereColumn('receipt_items.unit_price', '>', 'min_prices.min_price')
            ->select([
                'receipt_items.id as receipt_item_id',
                'products.id as product_id',
                'products.name as product_name',
                'receipts.vendor',
                'receipt_items.unit_price as paid_price',
                'receipt_items.quantity',
                'receipts.currency',
                'receipts.receipt_date',
            ])
            ->selectRaw('min_prices.min_price as cheapest_price')
            ->selectRaw('(receipt_items.unit_price - min_prices.min_price) * receipt_items.quantity as potential_savings')
            ->orderByDesc('potential_savings')
            ->limit($limit);

        $this->applyReceiptDateRange($query, $startDate, $endDate);
        $this->consumptionService->applyScope($query, $scope);

        return $query->get()->map(function (object $row) use ($cheapestVendors): object {
            $row->cheapest_vendor = $cheapestVendors->get($row->product_id)?->vendor;
            $row->potential_savings = round((float) $row->potential_savings, 2);

            return $row;
        });
    }

    /**
     * Cheapest vendor per product with the user's observed price range.
     *
     * @return Collection<int, object>
     */
    public function cheapestVendors(int $userId, int $limit = 20): Collection
    {
        $scope = $this->scopeFor($userId);
        $cheapestVendors = $this->cheapestVendorByProduct($userId, $scope);
        $minPrices = $this->minPriceSubquery($userId, $scope);

        return Product::query()
            ->joinSub($minPrices, 'min_prices', function ($join): void {
                $join->on('products.id', '=', 'min_prices.product_id');
            })
            ->where('products.user_id', $userId)
            ->select([
                'products.id as product_id',
                'products.name as product_name',
            ])
            ->selectRaw('min_prices.min_price as cheapest_price')
            ->selectRaw('min_prices.vendor_count')
            ->selectRaw('min_prices.max_price')
            ->orderBy('products.name')
            ->limit($limit)
            ->get()
            ->map(function (object $row) use ($cheapestVendors): object {
                $row->cheapest_vendor = $cheapestVendors->get($row->product_id)?->vendor;

                return $row;
            });
    }

    /**
     * Price history for a single product, ordered by observation date.
     *
     * @return Collection<int, PriceObservation>
     */
    public function priceHistory(int $userId, int $productId): Collection
    {
        return $this->observations($userId, $this->productPageScope($userId))
            ->where('price_observations.product_id', $productId)
            ->orderBy('price_observations.observed_at')
            ->orderBy('price_observations.id')
            ->get(['price_observations.*']);
    }

    /**
     * Price trend direction for products with enough observations.
     *
     * @return Collection<int, object>
     */
    public function priceTrends(int $userId, int $limit = 10): Collection
    {
        $observations = $this->observations($userId, $this->scopeFor($userId))
            ->select([
                'price_observations.product_id',
                'products.name as product_name',
                'price_observations.unit_price',
                'price_observations.observed_at',
            ])
            ->orderBy('price_observations.product_id')
            ->orderByDesc('price_observations.observed_at')
            ->get()
            ->groupBy('product_id');

        return $observations
            ->map(function (Collection $rows, int $productId): ?object {
                if ($rows->count() < 2) {
                    return null;
                }

                $latest = (float) $rows->first()->unit_price;
                $previous = (float) $rows->skip(1)->first()->unit_price;
                $change = round($latest - $previous, 4);
                $changePercent = $previous > 0 ? round(($change / $previous) * 100, 1) : null;

                return (object) [
                    'product_id' => $productId,
                    'product_name' => $rows->first()->product_name,
                    'latest_price' => $latest,
                    'previous_price' => $previous,
                    'change' => $change,
                    'change_percent' => $changePercent,
                    'direction' => $change > 0 ? 'rising' : ($change < 0 ? 'falling' : 'stable'),
                ];
            })
            ->filter()
            ->sortByDesc(fn (object $row) => abs($row->change))
            ->take($limit)
            ->values();
    }

    /**
     * Product detail: metadata, price history by vendor, and purchase history.
     *
     * @return array{product: Product, price_history: Collection<int, PriceObservation>, by_vendor: Collection<int, object>, purchases: Collection<int, object>}
     */
    public function productDetail(int $userId, Product $product): array
    {
        $scope = $this->productPageScope($userId);
        $priceHistory = $this->priceHistory($userId, $product->id);

        $byVendor = $this->observations($userId, $scope)
            ->where('price_observations.product_id', $product->id)
            ->select('price_observations.vendor')
            ->selectRaw('MIN(price_observations.unit_price) as min_price')
            ->selectRaw('MAX(price_observations.unit_price) as max_price')
            ->selectRaw('AVG(price_observations.unit_price) as avg_price')
            ->selectRaw('COUNT(*) as observation_count')
            ->groupBy('price_observations.vendor')
            ->orderBy('min_price')
            ->get();

        $purchases = ReceiptItem::query()
            ->join('receipts', fn ($join) => $join->on('receipt_items.receipt_id', '=', 'receipts.id')->whereNull('receipts.duplicate_of_id'))
            ->where('receipt_items.product_id', $product->id)
            ->where('receipts.user_id', $userId)
            ->where('receipt_items.line_type', LineItemType::Product->value)
            ->tap(fn (Builder $query) => $this->consumptionService->applyScope($query, $scope))
            ->select([
                'receipt_items.id',
                'receipt_items.quantity',
                'receipt_items.unit_price',
                'receipt_items.total',
                'receipts.vendor',
                'receipts.receipt_date',
                'receipts.currency',
            ])
            ->orderByDesc('receipts.receipt_date')
            ->get();

        return [
            'product' => $product->load('category'),
            'price_history' => $priceHistory,
            'by_vendor' => $byVendor,
            'purchases' => $purchases,
        ];
    }

    private function minPriceSubquery(int $userId, ConsumptionScope $scope): Builder
    {
        return $this->observations($userId, $scope)
            ->groupBy('price_observations.product_id')
            ->select('price_observations.product_id')
            ->selectRaw('MIN(price_observations.unit_price) as min_price')
            ->selectRaw('MAX(price_observations.unit_price) as max_price')
            ->selectRaw('COUNT(DISTINCT price_observations.vendor) as vendor_count');
    }

    /**
     * @return Collection<int, object{product_id: int, cheapest_vendor: ?string}>
     */
    private function cheapestVendorByProduct(int $userId, ConsumptionScope $scope): Collection
    {
        return $this->observations($userId, $scope)
            ->orderBy('price_observations.product_id')
            ->orderBy('price_observations.unit_price')
            ->orderByDesc('price_observations.observed_at')
            ->get(['price_observations.product_id', 'price_observations.vendor'])
            ->unique('product_id')
            ->keyBy('product_id');
    }

    /**
     * The user's price observations from product lines on receipts within $scope.
     *
     * @return Builder<PriceObservation>
     */
    private function observations(int $userId, ConsumptionScope $scope): Builder
    {
        $query = PriceObservation::query()
            ->join('receipt_items', 'price_observations.receipt_item_id', '=', 'receipt_items.id')
            ->join('receipts', fn ($join) => $join->on('receipt_items.receipt_id', '=', 'receipts.id')->whereNull('receipts.duplicate_of_id'))
            ->join('products', 'price_observations.product_id', '=', 'products.id')
            ->where('products.user_id', $userId)
            ->where('receipts.user_id', $userId)
            ->where('receipt_items.line_type', LineItemType::Product->value);

        $this->consumptionService->applyScope($query, $scope);

        return $query;
    }

    private function scopeFor(int $userId): ConsumptionScope
    {
        return $this->scopes[$userId] ??= ($user = User::query()->find($userId)) !== null
            ? $this->userSettingsService->consumptionScope($user)
            : ConsumptionScope::default();
    }

    /**
     * A single product's page: every purchase of it, but still one currency only.
     */
    private function productPageScope(int $userId): ConsumptionScope
    {
        return new ConsumptionScope(includeBusiness: true, currency: $this->scopeFor($userId)->currency);
    }

    private function applyReceiptDateRange(Builder $query, ?string $startDate, ?string $endDate): void
    {
        if ($startDate) {
            $query->where('receipts.receipt_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('receipts.receipt_date', '<=', $endDate.' 23:59:59');
        }
    }
}
