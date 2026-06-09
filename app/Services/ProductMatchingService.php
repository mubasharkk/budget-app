<?php

namespace App\Services;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductMatchingService
{
    public function __construct(private LlmService $llmService) {}

    /**
     * Match all line items on a processed receipt to canonical products and record price observations.
     */
    public function matchReceipt(Receipt $receipt): void
    {
        $receipt->loadMissing('items.category');

        $items = $receipt->items->filter(fn (ReceiptItem $item) => filled($item->name));

        if ($items->isEmpty()) {
            return;
        }

        $userId = $receipt->user_id;
        $existingProducts = $this->existingProductsForUser($userId);
        $lineItems = $this->formatLineItemsForPrompt($items);

        $result = $this->llmService->matchLineItemsToProducts($lineItems, $existingProducts);

        if (! $result['success']) {
            throw new \RuntimeException('Product matching failed: '.($result['error'] ?? 'Unknown error'));
        }

        $matches = collect($result['data']['matches'] ?? [])->keyBy('receipt_item_id');

        foreach ($items as $item) {
            $match = $matches->get($item->id);

            if (! $match) {
                Log::warning('No product match returned for receipt item', [
                    'receipt_item_id' => $item->id,
                    'receipt_id' => $receipt->id,
                ]);

                continue;
            }

            $product = $this->resolveProduct($userId, $match, $item);
            $item->update(['product_id' => $product->id]);

            $this->recordPriceObservation($product, $item, $receipt);
        }
    }

    /**
     * @return array<int, array{id: int, name: string, normalized_name: string, brand: ?string, unit: ?string, size: ?string}>
     */
    private function existingProductsForUser(int $userId): array
    {
        return Product::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name', 'normalized_name', 'brand', 'unit', 'size'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'normalized_name' => $product->normalized_name,
                'brand' => $product->brand,
                'unit' => $product->unit,
                'size' => $product->size,
            ])
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ReceiptItem>  $items
     * @return array<int, array{receipt_item_id: int, name: string, unit_price: float, quantity: float, category: ?string}>
     */
    private function formatLineItemsForPrompt($items): array
    {
        return $items->map(fn (ReceiptItem $item): array => [
            'receipt_item_id' => $item->id,
            'name' => $item->name,
            'unit_price' => (float) $item->unit_price,
            'quantity' => (float) $item->quantity,
            'category' => $item->category?->name,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function resolveProduct(int $userId, array $match, ReceiptItem $item): Product
    {
        if (($match['action'] ?? '') === 'existing' && ! empty($match['product_id'])) {
            $product = Product::query()
                ->where('user_id', $userId)
                ->find($match['product_id']);

            if ($product) {
                return $product;
            }
        }

        $productData = $match['product'] ?? [];
        $name = $productData['name'] ?? $item->name;
        $normalizedName = $productData['normalized_name'] ?? Str::slug($name);

        return Product::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'normalized_name' => $normalizedName,
            ],
            [
                'name' => $name,
                'brand' => $productData['brand'] ?? null,
                'unit' => $productData['unit'] ?? null,
                'size' => $productData['size'] ?? null,
                'category_id' => $item->category_id,
                'attributes' => null,
            ],
        );
    }

    private function recordPriceObservation(Product $product, ReceiptItem $item, Receipt $receipt): void
    {
        PriceObservation::query()->updateOrCreate(
            ['receipt_item_id' => $item->id],
            [
                'product_id' => $product->id,
                'vendor' => $receipt->vendor,
                'unit_price' => $item->unit_price,
                'currency' => $receipt->currency ?? 'EUR',
                'observed_at' => $receipt->receipt_date?->toDateString() ?? now()->toDateString(),
            ],
        );
    }
}
