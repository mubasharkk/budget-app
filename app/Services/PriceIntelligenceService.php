<?php

namespace App\Services;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PriceIntelligenceService
{
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
        $cheapestVendors = $this->cheapestVendorByProduct($userId);
        $minPrices = $this->minPriceSubquery($userId);

        $query = ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->join('products', 'receipt_items.product_id', '=', 'products.id')
            ->joinSub($minPrices, 'min_prices', function ($join): void {
                $join->on('products.id', '=', 'min_prices.product_id');
            })
            ->where('products.user_id', $userId)
            ->whereNotNull('receipt_items.product_id')
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
        $cheapestVendors = $this->cheapestVendorByProduct($userId);
        $minPrices = $this->minPriceSubquery($userId);

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
        return PriceObservation::query()
            ->whereHas('product', fn (Builder $query) => $query->where('user_id', $userId))
            ->where('product_id', $productId)
            ->orderBy('observed_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Price trend direction for products with enough observations.
     *
     * @return Collection<int, object>
     */
    public function priceTrends(int $userId, int $limit = 10): Collection
    {
        $observations = PriceObservation::query()
            ->join('products', 'price_observations.product_id', '=', 'products.id')
            ->where('products.user_id', $userId)
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
        $priceHistory = $this->priceHistory($userId, $product->id);

        $byVendor = PriceObservation::query()
            ->where('product_id', $product->id)
            ->select('vendor')
            ->selectRaw('MIN(unit_price) as min_price')
            ->selectRaw('MAX(unit_price) as max_price')
            ->selectRaw('AVG(unit_price) as avg_price')
            ->selectRaw('COUNT(*) as observation_count')
            ->groupBy('vendor')
            ->orderBy('min_price')
            ->get();

        $purchases = ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->where('receipt_items.product_id', $product->id)
            ->where('receipts.user_id', $userId)
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

    private function minPriceSubquery(int $userId): Builder
    {
        return PriceObservation::query()
            ->join('products', 'price_observations.product_id', '=', 'products.id')
            ->where('products.user_id', $userId)
            ->groupBy('price_observations.product_id')
            ->select('price_observations.product_id')
            ->selectRaw('MIN(price_observations.unit_price) as min_price')
            ->selectRaw('MAX(price_observations.unit_price) as max_price')
            ->selectRaw('COUNT(DISTINCT price_observations.vendor) as vendor_count');
    }

    /**
     * @return Collection<int, object{product_id: int, cheapest_vendor: ?string}>
     */
    private function cheapestVendorByProduct(int $userId): Collection
    {
        return PriceObservation::query()
            ->join('products', 'price_observations.product_id', '=', 'products.id')
            ->where('products.user_id', $userId)
            ->orderBy('price_observations.product_id')
            ->orderBy('price_observations.unit_price')
            ->orderByDesc('price_observations.observed_at')
            ->get(['price_observations.product_id', 'price_observations.vendor'])
            ->unique('product_id')
            ->keyBy('product_id');
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
