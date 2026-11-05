<?php

namespace App\Domain\Deals\Services;

use App\Domain\Deals\Data\Offer;
use App\Domain\Deals\Exceptions\DealSourceUnavailable;
use App\Enums\Retailer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Aldi Nord's weekly offers, read from the JSON its offer pages embed for the
 * Next.js app (props.pageProps.apiData → OFFER_GET → algoliaDataMap). There is
 * no public API, so a change to the page shows up as DealSourceUnavailable.
 * Aldi Nord's offers are the same in every store, so the pages are fetched
 * once an hour, not once per postal code.
 */
class AldiNordOfferFeed implements RetailerOfferFeed
{
    /**
     * This week's and next week's offer pages.
     */
    public const PAGES = ['angebote.html', 'angebote-vorschau.html'];

    public const CACHE_KEY = 'deals:aldi-nord:products';

    private const CACHE_SECONDS = 3600;

    public function name(): string
    {
        return 'aldi_nord';
    }

    public function retailer(): Retailer
    {
        return Retailer::Aldi;
    }

    public function offers(): array
    {
        $products = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => array_merge(
            ...array_map(fn (string $page): array => $this->products($page), self::PAGES),
        ));

        return array_values(array_filter(array_map(
            fn (mixed $product): ?Offer => is_array($product) ? $this->toOffer($product) : null,
            $products,
        )));
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws DealSourceUnavailable
     */
    private function products(string $page): array
    {
        try {
            $response = Http::withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
                ->withUserAgent('Mozilla/5.0 (compatible; BudgetWise deals sync)')
                ->timeout(30)
                ->get($this->webUrl().'/'.$page);
        } catch (ConnectionException $exception) {
            throw new DealSourceUnavailable('Aldi Nord could not be reached.', previous: $exception);
        }

        if ($response->failed()) {
            throw new DealSourceUnavailable("Aldi Nord responded with HTTP {$response->status()} for {$page}.");
        }

        if (preg_match('#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $response->body(), $match) !== 1) {
            throw new DealSourceUnavailable("Aldi Nord's {$page} no longer embeds its offer data.");
        }

        $apiData = json_decode((string) data_get(json_decode($match[1], true), 'props.pageProps.apiData'), true);

        foreach ((array) $apiData as $entry) {
            if (is_array($entry) && ($entry[0] ?? null) === 'OFFER_GET') {
                return array_values((array) data_get($entry, '1.res.algoliaDataMap', []));
            }
        }

        throw new DealSourceUnavailable("Aldi Nord's {$page} has no offer list.");
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function toOffer(array $product): ?Offer
    {
        $promotion = data_get($product, 'promotionPrices.0') ?? data_get($product, 'currentPrice');
        $price = data_get($promotion, 'priceValue');
        $id = data_get($product, 'objectID');
        $title = $this->text(data_get($product, 'name'));
        $validFrom = $this->text(data_get($promotion, 'validFromLocalDate'));

        if (! is_numeric($price) || (float) $price <= 0 || $id === null || $title === null || data_get($product, 'isAvailable') === false) {
            return null;
        }

        $oldPrice = data_get($promotion, 'strikePrice.strikePriceValue');
        $slug = $this->text(data_get($product, 'productSlug'));
        [$unitPrice, $unitPriceUnit] = $this->unitPrice(data_get($promotion, 'basePrice.0'));

        return new Offer(
            externalId: $validFrom !== null ? "{$id}:{$validFrom}" : (string) $id,
            retailer: Retailer::Aldi,
            advertiserName: 'ALDI Nord',
            title: $title,
            brand: $this->text(data_get($product, 'brandName')),
            description: $this->text(preg_replace('/\s+/u', ' ', (string) data_get($product, 'shortDescription'))),
            price: (float) $price,
            oldPrice: is_numeric($oldPrice) && (float) $oldPrice > (float) $price ? (float) $oldPrice : null,
            unit: $this->text(data_get($product, 'salesUnit')),
            validFrom: $validFrom,
            validTo: $this->text(data_get($promotion, 'validUntilLocalDate')),
            raw: $product,
            externalUrl: $slug !== null ? $this->webUrl().'/produkt/'.rawurlencode($slug).'.html' : null,
            unitPrice: $unitPrice,
            unitPriceUnit: $unitPriceUnit,
        );
    }

    /**
     * Aldi's own price per kg, litre or piece; "kg (ATG)" is per kg drained weight.
     *
     * @return array{0: ?float, 1: ?string}
     */
    private function unitPrice(mixed $basePrice): array
    {
        $value = data_get($basePrice, 'basePriceValue');
        $unit = match (strtok(mb_strtolower(trim((string) data_get($basePrice, 'basePriceScale'))), ' ')) {
            'kg' => 'kg',
            'liter', 'l' => 'l',
            'stück' => 'Stück',
            default => null,
        };

        return is_numeric($value) && (float) $value > 0 && $unit !== null ? [round((float) $value, 2), $unit] : [null, null];
    }

    private function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function webUrl(): string
    {
        return rtrim((string) config('providers.aldi_nord.web_url'), '/');
    }
}
