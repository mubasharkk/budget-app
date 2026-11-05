<?php

namespace App\Domain\Deals\Services;

use App\Domain\Deals\Data\Offer;
use App\Domain\Deals\Exceptions\DealSourceUnavailable;
use App\Enums\Retailer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Unofficial marktguru aggregator (see docs/deals-page-plan.md). The field
 * mapping reads each value from its known key with fallbacks, because the
 * payload shape is not contractually stable.
 */
class MarktguruDealSource implements DealSource
{
    public function name(): string
    {
        return 'marktguru';
    }

    public function search(string $postalCode, string $searchTerm, int $limit, int $offset = 0): array
    {
        try {
            $response = Http::withHeaders([
                'X-Apikey' => (string) config('providers.marktguru.api_key'),
                'X-Clientkey' => (string) config('providers.marktguru.client_key'),
            ])->acceptJson()->timeout(20)->get(rtrim((string) config('providers.marktguru.base_url'), '/').'/offers/search', [
                'as' => 'web',
                'q' => $searchTerm,
                'zipCode' => $postalCode,
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (ConnectionException $exception) {
            throw new DealSourceUnavailable('marktguru could not be reached.', previous: $exception);
        }

        if ($response->failed()) {
            throw new DealSourceUnavailable("marktguru responded with HTTP {$response->status()}.");
        }

        $rows = (array) $response->json('results', []);
        $offers = [];

        foreach ($rows as $row) {
            $offer = is_array($row) ? $this->toOffer($row) : null;

            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        $total = $response->json('totalResults');

        return [
            'offers' => $offers,
            'has_more' => is_numeric($total) ? $offset + count($rows) < (int) $total : count($rows) >= $limit,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toOffer(array $row): ?Offer
    {
        $advertiserName = $this->text(data_get($row, 'advertisers.0.name'));
        $retailer = Retailer::fromAdvertiserName($advertiserName);
        $price = data_get($row, 'price');

        if ($retailer === null || ! is_numeric($price) || data_get($row, 'id') === null) {
            return null;
        }

        $brand = $this->text(data_get($row, 'brand.name'));
        $description = $this->text(data_get($row, 'description'));
        $title = $this->text(data_get($row, 'product.name')) ?? $description ?? $brand;

        if ($title === null) {
            return null;
        }

        $oldPrice = data_get($row, 'oldPrice');

        return new Offer(
            externalId: (string) data_get($row, 'id'),
            retailer: $retailer,
            advertiserName: $advertiserName,
            title: $title,
            brand: $brand,
            description: $description === $title ? null : $description,
            price: (float) $price,
            oldPrice: is_numeric($oldPrice) && (float) $oldPrice > 0 ? (float) $oldPrice : null,
            unit: $this->text(data_get($row, 'unit.shortName') ?? data_get($row, 'unit.name')),
            validFrom: $this->date(data_get($row, 'validityDates.0.from')),
            validTo: $this->date(data_get($row, 'validityDates.0.to')),
            raw: $row,
            externalUrl: $this->url(data_get($row, 'externalUrl')),
            sourceUrl: $this->offerPageUrl((string) data_get($row, 'id')),
        );
    }

    private function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    /**
     * marktguru's own page for the offer (its web app routes /offers/{id}).
     */
    private function offerPageUrl(string $id): string
    {
        return rtrim((string) config('providers.marktguru.web_url'), '/').'/offers/'.rawurlencode($id);
    }

    /**
     * The retailer's own page for the offer, when the source has one; only http(s) links are kept.
     */
    private function url(mixed $value): ?string
    {
        $url = $this->text($value);

        if ($url === null || mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
