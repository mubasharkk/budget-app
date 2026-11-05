<?php

namespace App\Domain\Deals\Services;

use App\Domain\Deals\Data\Offer;
use App\Domain\Deals\Exceptions\DealSourceUnavailable;
use App\Enums\Retailer;
use App\Models\Deal;
use App\Models\DealPostalCode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DealSyncService
{
    public const PAGE_SIZE = 100;

    /**
     * Upper bound per retailer per postal code, so a runaway source can't loop forever.
     */
    public const MAX_PAGES = 10;

    /**
     * @param  iterable<int, RetailerOfferFeed>  $offerFeeds  retailers' own catalogues, used where the main source has none of their offers
     */
    public function __construct(
        private DealSource $dealSource,
        private DealCategorizer $dealCategorizer,
        private UnitPriceCalculator $unitPriceCalculator,
        private iterable $offerFeeds = [],
    ) {}

    /**
     * Fetch every current offer for the postal code and store it. Offers no
     * longer listed there are unlinked; deals left with no postal code are
     * deleted. Nothing is written if the main source fails; a failing offer
     * feed only keeps that feed's stored offers as they were.
     *
     * @return int number of offers listed for the postal code after the sync
     *
     * @throws DealSourceUnavailable
     */
    public function syncPostalCode(string $postalCode): int
    {
        $offersBySource = $this->fetchAll($postalCode);
        $now = now();

        DB::transaction(function () use ($offersBySource, $postalCode, $now): void {
            foreach ($offersBySource as $source => $offers) {
                $this->store($source, $offers, $postalCode, $now);
            }

            $this->deleteUnlinkedDeals();
        });

        return $offersBySource->sum(fn (Collection $offers): int => $offers->count());
    }

    /**
     * Upsert one source's offers, link them to the postal code and unlink that
     * source's offers the postal code no longer lists.
     *
     * @param  Collection<string, Offer>  $offers
     */
    private function store(string $source, Collection $offers, string $postalCode, \DateTimeInterface $now): void
    {
        foreach ($offers->chunk(200) as $chunk) {
            Deal::query()->upsert(
                $chunk->map(fn (Offer $offer): array => $this->toRow($offer, $source, $now))->values()->all(),
                ['source', 'external_id'],
                ['retailer', 'category_id', 'advertiser_name', 'title', 'brand', 'description', 'external_url', 'source_url', 'price', 'old_price', 'discount_percent', 'unit', 'unit_price', 'unit_price_unit', 'valid_from', 'valid_to', 'raw', 'fetched_at', 'updated_at'],
            );
        }

        $dealIds = $offers->isEmpty() ? collect() : Deal::query()
            ->where('source', $source)
            ->whereIn('external_id', $offers->keys()->all())
            ->pluck('id');

        foreach ($dealIds->chunk(500) as $chunk) {
            DealPostalCode::query()->upsert(
                $chunk->map(fn (int $dealId): array => [
                    'deal_id' => $dealId,
                    'postal_code' => $postalCode,
                    'fetched_at' => $now,
                ])->values()->all(),
                ['deal_id', 'postal_code'],
                ['fetched_at'],
            );
        }

        DealPostalCode::query()
            ->where('postal_code', $postalCode)
            ->where('fetched_at', '<', $now)
            ->whereHas('deal', fn ($deal) => $deal->where('source', $source))
            ->delete();
    }

    /**
     * Delete offers whose validity ended before today.
     */
    public function pruneExpired(): int
    {
        return Deal::query()->whereDate('valid_to', '<', now()->toDateString())->delete();
    }

    /**
     * Distinct postal codes users have saved — the regions worth syncing.
     *
     * @return Collection<int, string>
     */
    public function postalCodesInUse(): Collection
    {
        return User::query()
            ->whereNotNull('postal_code')
            ->distinct()
            ->orderBy('postal_code')
            ->pluck('postal_code');
    }

    public function hasDealsFor(string $postalCode): bool
    {
        return DealPostalCode::query()->where('postal_code', $postalCode)->exists();
    }

    /**
     * Offers to store per source, keyed by external id. An offer feed is used
     * only when the main source has none of its retailer's offers here (so an
     * Aldi Süd region never gets Aldi Nord's offers); otherwise it gets an
     * empty list, which unlinks its offers from this postal code. A feed that
     * fails is left out, keeping what it stored before.
     *
     * @return Collection<string, Collection<string, Offer>>
     *
     * @throws DealSourceUnavailable
     */
    private function fetchAll(string $postalCode): Collection
    {
        $mainOffers = $this->fetchFromMainSource($postalCode);
        $offersBySource = collect([$this->dealSource->name() => $mainOffers]);

        foreach ($this->offerFeeds as $feed) {
            if ($mainOffers->contains(fn (Offer $offer): bool => $offer->retailer === $feed->retailer())) {
                $offersBySource->put($feed->name(), collect());

                continue;
            }

            try {
                $offersBySource->put($feed->name(), collect($feed->offers())
                    ->filter(fn (Offer $offer): bool => $offer->retailer === $feed->retailer())
                    ->keyBy(fn (Offer $offer): string => $offer->externalId));
            } catch (DealSourceUnavailable $exception) {
                Log::warning('Deal offer feed unavailable', ['feed' => $feed->name(), 'postal_code' => $postalCode, 'error' => $exception->getMessage()]);
            }
        }

        return $offersBySource;
    }

    /**
     * Every page of the main source's offers for each supported retailer, keyed by external id.
     *
     * @return Collection<string, Offer>
     *
     * @throws DealSourceUnavailable
     */
    private function fetchFromMainSource(string $postalCode): Collection
    {
        $offers = collect();

        foreach (Retailer::cases() as $retailer) {
            for ($page = 0; $page < self::MAX_PAGES; $page++) {
                $result = $this->dealSource->search($postalCode, $retailer->label(), self::PAGE_SIZE, $page * self::PAGE_SIZE);

                foreach ($result['offers'] as $offer) {
                    if ($offer->retailer === $retailer) {
                        $offers->put($offer->externalId, $offer);
                    }
                }

                if (! $result['has_more']) {
                    break;
                }
            }
        }

        return $offers;
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Offer $offer, string $source, \DateTimeInterface $now): array
    {
        $unitPrice = $offer->unitPrice !== null && $offer->unitPriceUnit !== null
            ? ['unit_price' => $offer->unitPrice, 'unit_price_unit' => $offer->unitPriceUnit]
            : $this->unitPriceCalculator->calculate($offer->price, $offer->raw, $offer->title, $offer->description, $offer->unit);

        return [
            'source' => $source,
            'external_id' => $offer->externalId,
            'retailer' => $offer->retailer->value,
            'category_id' => $this->dealCategorizer->categoryIdFor($offer->title, $offer->brand, $offer->description, $offer->raw),
            'advertiser_name' => $offer->advertiserName,
            'title' => mb_substr($offer->title, 0, 255),
            'brand' => $offer->brand !== null ? mb_substr($offer->brand, 0, 255) : null,
            'description' => $offer->description,
            'external_url' => $offer->externalUrl,
            'source_url' => $offer->sourceUrl,
            'price' => $offer->price,
            'old_price' => $offer->oldPrice,
            'discount_percent' => $offer->discountPercent(),
            'unit' => $offer->unit !== null ? mb_substr($offer->unit, 0, 30) : null,
            'unit_price' => $unitPrice['unit_price'],
            'unit_price_unit' => $unitPrice['unit_price_unit'],
            'valid_from' => $offer->validFrom,
            'valid_to' => $offer->validTo,
            'raw' => json_encode($offer->raw, JSON_UNESCAPED_UNICODE),
            'fetched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function deleteUnlinkedDeals(): void
    {
        Deal::query()->whereDoesntHave('postalCodes')->delete();
    }
}
