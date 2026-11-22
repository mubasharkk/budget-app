<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumedLine;
use App\Domain\Deals\Services\ProductDealMatcher;
use App\Domain\Products\Support\ProductQuantity;
use App\Enums\Retailer;
use App\Models\Deal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Where the next purchase could cost less, without AI. Only like is compared
 * with like: an offer by its price per l, kg or piece against what the user
 * usually pays for that product, and another shop only for the identical
 * product. Anything that cannot be compared is left out, never guessed.
 */
class ConsumptionSavingsService
{
    public const PRICE_DAYS = 90;

    /**
     * Purchases needed before a price counts as the usual one.
     */
    public const MIN_PURCHASES = 2;

    /**
     * Cheaper than this share of the usual price is taken for another product
     * or a misread, not for a bargain.
     */
    public const PLAUSIBLE_SHARE = 0.5;

    public const MIN_SAVING = 0.05;

    public function __construct(private ProductDealMatcher $productDealMatcher) {}

    /**
     * The best saving per thing the user buys, biggest first, each offer once.
     *
     * @param  Collection<int, ConsumedLine>  $lines
     * @param  Collection<int, Deal>  $deals  current offers in the user's currency and area
     * @return array<int, array<string, mixed>>
     */
    public function find(Collection $lines, Collection $deals): array
    {
        // Read once: casting every offer again for every product is too slow.
        $comparableDeals = $deals
            ->map(fn (Deal $deal): array => [
                'deal' => $deal,
                'price' => (float) $deal->unit_price,
                'unit' => ProductQuantity::baseUnit($deal->unit_price_unit),
            ])
            ->filter(fn (array $offer): bool => $offer['price'] > 0 && $offer['unit'] !== null)
            ->toBase()
            ->groupBy('unit');

        $candidates = $lines
            ->filter(fn (ConsumedLine $line): bool => $line->productId !== null && $line->total > 0 && $line->quantity > 0)
            ->groupBy(fn (ConsumedLine $line): int => $line->productId)
            ->filter(fn (Collection $purchases): bool => $purchases->count() >= self::MIN_PURCHASES && $this->hasTrustworthyQuantities($purchases))
            ->flatMap(fn (Collection $purchases): array => [
                ...$this->offerSavings($purchases, $comparableDeals),
                ...$this->shopSavings($purchases),
            ])
            ->sortByDesc('saving');

        $found = [];
        $usedDeals = [];

        foreach ($candidates as $candidate) {
            $dealId = $candidate['offer']['id'] ?? null;

            if (isset($found[$candidate['key']]) || ($dealId !== null && isset($usedDeals[$dealId]))) {
                continue;
            }

            $found[$candidate['key']] = $candidate;

            if ($dealId !== null) {
                $usedDeals[$dealId] = true;
            }
        }

        return array_values($found);
    }

    /**
     * A weighed product always read as whole numbers had its weight lost
     * ("1" for 1.234 kg), so its price per kilo is unknown.
     *
     * @param  Collection<int, ConsumedLine>  $purchases
     */
    private function hasTrustworthyQuantities(Collection $purchases): bool
    {
        return ! $purchases->first()->isWeighed()
            || $purchases->contains(fn (ConsumedLine $line): bool => fmod($line->quantity, 1.0) !== 0.0);
    }

    /**
     * @param  Collection<int, ConsumedLine>  $purchases  of one product
     * @param  Collection<string, Collection<int, array{deal: Deal, price: float, unit: string}>>  $dealsByUnit
     * @return array<int, array<string, mixed>>
     */
    private function offerSavings(Collection $purchases, Collection $dealsByUnit): array
    {
        $product = $purchases->first();

        // Pieces without a pack size may be packs, so their piece price is unknown.
        if (! $product->hasPackSize() && ! $product->isWeighed()) {
            return [];
        }

        $perItem = ProductQuantity::perUnit($product->productUnit, $product->productSize);

        if ($perItem === null || ! $dealsByUnit->has($perItem['unit'])) {
            return [];
        }

        $usual = $this->usualPrice($purchases) / $perItem['amount'];
        $amount = $this->usualQuantity($purchases) * $perItem['amount'];
        $cheaper = $dealsByUnit->get($perItem['unit'])
            ->filter(fn (array $offer): bool => $offer['price'] < $usual && $offer['price'] >= $usual * self::PLAUSIBLE_SHARE)
            ->pluck('deal');
        $names = $purchases->map(fn (ConsumedLine $line): string => $line->name)->push($product->productName)->unique()->all();

        return $this->productDealMatcher->matchingWholeWords($names, $product->categoryId, $cheaper)
            ->map(fn (Deal $deal): array => [
                ...$this->saving($product, 'offer', $usual, (float) $deal->unit_price, $amount, $perItem['unit']),
                'shop' => $deal->retailer->label(),
                'offer' => [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'retailer_label' => $deal->retailer->label(),
                    'external_url' => $deal->external_url,
                    'source_url' => $deal->source_url,
                    'valid_to' => $deal->valid_to?->toDateString(),
                ],
            ])
            ->filter(fn (array $saving): bool => $saving['saving'] >= self::MIN_SAVING)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ConsumedLine>  $purchases  of one product
     * @return array<int, array<string, mixed>>
     */
    private function shopSavings(Collection $purchases): array
    {
        $shops = $purchases
            ->filter(fn (ConsumedLine $line): bool => $line->vendor !== null)
            ->groupBy(fn (ConsumedLine $line): string => $this->shopName($line->vendor));

        if ($shops->count() < 2) {
            return [];
        }

        $usualShop = $shops->sortByDesc(fn (Collection $bought): int => $bought->count())->first();

        if ($usualShop->count() < self::MIN_PURCHASES) {
            return [];
        }

        $product = $purchases->first();
        $usual = $this->usualPrice($usualShop);

        return $shops
            ->filter(fn (Collection $bought): bool => $bought->count() >= self::MIN_PURCHASES
                && $this->usualPrice($bought) < $usual
                && $this->usualPrice($bought) >= $usual * self::PLAUSIBLE_SHARE)
            ->map(fn (Collection $bought): array => [
                ...$this->saving($product, 'shop', $usual, $this->usualPrice($bought), $this->usualQuantity($purchases), $product->isWeighed() ? ProductQuantity::KILOGRAMS : 'item'),
                'shop' => $bought->last()->vendor,
                'usual_shop' => $usualShop->last()->vendor,
                'offer' => null,
            ])
            ->filter(fn (array $saving): bool => $saving['saving'] >= self::MIN_SAVING)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function saving(ConsumedLine $product, string $kind, float $usual, float $price, float $amount, string $unit): array
    {
        return [
            'key' => $product->key,
            'label' => $product->label,
            'product_name' => $product->productName,
            'kind' => $kind,
            'unit' => $unit,
            'usual_price' => round($usual, 2),
            'price' => round($price, 2),
            'amount_label' => $unit === 'item' ? ProductQuantity::label($amount, null).'×' : ProductQuantity::label($amount, $unit),
            'saving' => round(($usual - $price) * $amount, 2),
        ];
    }

    /**
     * @param  Collection<int, ConsumedLine>  $purchases
     */
    private function usualPrice(Collection $purchases): float
    {
        return (float) $purchases->map(fn (ConsumedLine $line): float => $line->total / $line->quantity)->median();
    }

    /**
     * How much is bought on a day it is bought.
     *
     * @param  Collection<int, ConsumedLine>  $purchases
     */
    private function usualQuantity(Collection $purchases): float
    {
        return (float) $purchases
            ->groupBy(fn (ConsumedLine $line): string => $line->date->toDateString())
            ->map(fn (Collection $day): float => $day->sum(fn (ConsumedLine $line): float => $line->quantity))
            ->median();
    }

    /**
     * "REWE" and "REWE Markt GmbH" are one shop.
     */
    private function shopName(string $vendor): string
    {
        $name = Str::lower(Str::squish($vendor));

        foreach (Retailer::cases() as $retailer) {
            if (preg_match('/\b'.$retailer->value.'\b/u', $name) === 1) {
                return $retailer->value;
            }
        }

        return $name;
    }
}
