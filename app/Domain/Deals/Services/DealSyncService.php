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

class DealSyncService
{
    public const PAGE_SIZE = 100;

    /**
     * Upper bound per retailer per postal code, so a runaway source can't loop forever.
     */
    public const MAX_PAGES = 10;

    public function __construct(
        private DealSource $dealSource,
        private DealCategorizer $dealCategorizer,
        private UnitPriceCalculator $unitPriceCalculator,
    ) {}

    /**
     * Fetch every current offer for the postal code and store it. Offers no
     * longer listed there are unlinked; deals left with no postal code are
     * deleted. Nothing is written if any fetch fails.
     *
     * @return int number of offers listed for the postal code after the sync
     *
     * @throws DealSourceUnavailable
     */
    public function syncPostalCode(string $postalCode): int
    {
        $offers = $this->fetchAll($postalCode);
        $source = $this->dealSource->name();
        $now = now();

        DB::transaction(function () use ($offers, $source, $postalCode, $now): void {
            foreach ($offers->chunk(200) as $chunk) {
                Deal::query()->upsert(
                    $chunk->map(fn (Offer $offer): array => $this->toRow($offer, $source, $now))->values()->all(),
                    ['source', 'external_id'],
                    ['retailer', 'category_id', 'advertiser_name', 'title', 'brand', 'description', 'external_url', 'source_url', 'price', 'old_price', 'discount_percent', 'unit', 'unit_price', 'unit_price_unit', 'valid_from', 'valid_to', 'raw', 'fetched_at', 'updated_at'],
                );
            }

            $dealIds = Deal::query()
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

            $this->deleteUnlinkedDeals();
        });

        return $offers->count();
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
     * Every page of offers for each supported retailer, keyed by external id.
     *
     * @return Collection<string, Offer>
     */
    private function fetchAll(string $postalCode): Collection
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
        $unitPrice = $this->unitPriceCalculator->calculate($offer->price, $offer->raw, $offer->title, $offer->description, $offer->unit);

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
