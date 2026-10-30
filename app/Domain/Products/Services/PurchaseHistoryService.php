<?php

namespace App\Domain\Products\Services;

use App\Domain\Products\Data\PurchasedProduct;
use App\Models\PriceObservation;
use App\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurchaseHistoryService
{
    public const LOOKBACK_DAYS = 180;

    /**
     * Products the user bought within the lookback window, most frequent first.
     *
     * @return Collection<int, PurchasedProduct>
     */
    public function recentProducts(int $userId, int $days = self::LOOKBACK_DAYS): Collection
    {
        return PriceObservation::query()
            ->with('product:id,user_id,name,normalized_name,category_id')
            ->whereHas('product', fn (Builder $product) => $product->where('user_id', $userId))
            ->whereDate('observed_at', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(function (Collection $observations): PurchasedProduct {
                /** @var PriceObservation $latest */
                $latest = $observations->first();

                return new PurchasedProduct(
                    productId: $latest->product->id,
                    name: $latest->product->name,
                    normalizedName: $latest->product->normalized_name,
                    categoryId: $latest->product->category_id,
                    purchaseCount: $observations->count(),
                    lastPaidPrice: (float) $latest->unit_price,
                    lastVendor: $latest->vendor,
                    lastPurchasedAt: $latest->observed_at->toDateString(),
                );
            })
            ->sortByDesc('purchaseCount')
            ->values();
    }

    /**
     * Category and subcategory ids of everything the user has bought.
     *
     * @return array<int, int>
     */
    public function purchasedCategoryIds(int $userId): array
    {
        $items = ReceiptItem::query()
            ->whereHas('receipt', fn (Builder $receipt) => $receipt->where('user_id', $userId)->whereNull('duplicate_of_id'))
            ->where(fn (Builder $query) => $query->whereNotNull('category_id')->orWhereNotNull('subcategory_id'))
            ->distinct()
            ->get(['category_id', 'subcategory_id']);

        return $items->pluck('category_id')
            ->merge($items->pluck('subcategory_id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
