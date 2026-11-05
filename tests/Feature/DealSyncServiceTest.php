<?php

namespace Tests\Feature;

use App\Domain\Deals\Exceptions\DealSourceUnavailable;
use App\Domain\Deals\Services\AldiNordOfferFeed;
use App\Domain\Deals\Services\DealSyncService;
use App\Enums\Retailer;
use App\Models\Deal;
use App\Models\DealPostalCode;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DealSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Offers the fake marktguru returns, keyed by search term.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $catalogue = [];

    /**
     * When set, the fake marktguru answers every request with this HTTP status.
     */
    private ?int $failWithStatus = null;

    /**
     * Products on the fake Aldi Nord offer pages, keyed by page.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $aldiNordPages = [];

    /**
     * When set, the fake Aldi Nord site answers with this HTTP status.
     */
    private ?int $aldiNordFailsWithStatus = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'providers.marktguru.base_url' => 'https://marktguru.test/api/v1',
            'providers.aldi_nord.web_url' => 'https://aldi-nord.test',
        ]);

        Http::fake(function (Request $request) {
            if (str_starts_with($request->url(), 'https://aldi-nord.test/')) {
                return $this->aldiNordFailsWithStatus !== null
                    ? Http::response('Server error', $this->aldiNordFailsWithStatus)
                    : Http::response($this->aldiNordPage($this->aldiNordPages[basename($request->url())] ?? []));
            }

            if ($this->failWithStatus !== null) {
                return Http::response('Server error', $this->failWithStatus);
            }

            $results = $this->catalogue[$request['q']] ?? [];
            $page = array_slice($results, (int) $request['offset'], (int) $request['limit']);

            return Http::response(['totalResults' => count($results), 'results' => $page]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function offer(int $id, string $advertiser, float $price, ?float $oldPrice = null): array
    {
        return [
            'id' => $id,
            'price' => $price,
            'oldPrice' => $oldPrice,
            'description' => "Offer {$id} description",
            'brand' => ['name' => 'Brand '.$id],
            'product' => ['name' => 'Product '.$id],
            'unit' => ['shortName' => 'kg'],
            'validityDates' => [['from' => '2026-10-26T00:00:00Z', 'to' => '2026-10-31T23:59:59Z']],
            'advertisers' => [['name' => $advertiser]],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function aldiNordPage(array $products): string
    {
        $apiData = json_encode([['OFFER_GET', ['req' => ['week' => 'current'], 'res' => [
            'algoliaDataMap' => collect($products)->keyBy('objectID')->all(),
        ]]]]);

        return '<html><body><script id="__NEXT_DATA__" type="application/json">'
            .json_encode(['props' => ['pageProps' => ['apiData' => $apiData]]])
            .'</script></body></html>';
    }

    /**
     * @return array<string, mixed>
     */
    private function aldiNordProduct(string $id, float $price, ?float $strikePrice = null, string $from = '2026-11-02'): array
    {
        return [
            'objectID' => $id,
            'name' => "Aldi product {$id}",
            'brandName' => 'GUT BIO',
            'shortDescription' => "Verschiedene Sorten\n\n\nIm Aufsteller",
            'salesUnit' => '180-g-Dose',
            'productSlug' => "aldi-product-{$id}",
            'isAvailable' => true,
            'promotionPrices' => [[
                'priceValue' => $price,
                'strikePrice' => $strikePrice !== null ? ['strikePriceValue' => $strikePrice] : [],
                'basePrice' => [['basePriceValue' => 7.72, 'basePriceScale' => 'kg (ATG)']],
                'validFromLocalDate' => $from,
                'validUntilLocalDate' => '2026-11-07',
            ]],
        ];
    }

    private function sync(string $postalCode): int
    {
        return app(DealSyncService::class)->syncPostalCode($postalCode);
    }

    public function test_offers_for_every_retailer_are_stored_with_their_raw_payload(): void
    {
        $this->catalogue = [
            'Lidl' => [$this->offer(1, 'Lidl', 1.99, 2.99), $this->offer(99, 'Rewe', 0.50)],
            'Aldi' => [$this->offer(2, 'ALDI SÜD', 0.89)],
            'Netto' => [$this->offer(3, 'Netto Marken-Discount', 3.49)],
            'Kaufland' => [$this->offer(4, 'Kaufland', 5.00)],
            'Edeka' => [$this->offer(5, 'EDEKA', 2.22), $this->offer(1, 'Lidl', 1.99, 2.99)],
            'Penny' => [$this->offer(6, 'PENNY', 1.49)],
            'REWE' => [$this->offer(7, 'REWE Center', 2.79)],
        ];

        $this->assertSame(7, $this->sync('10115'));

        $this->assertSame(7, Deal::count());
        $this->assertSame(Retailer::Penny, Deal::where('external_id', '6')->sole()->retailer);
        $this->assertSame(Retailer::Rewe, Deal::where('external_id', '7')->sole()->retailer);
        $this->assertDatabaseMissing('deals', ['external_id' => '99']);
        Http::assertSent(fn (Request $request): bool => ($request['zipCode'] ?? null) === '10115' && ($request['q'] ?? null) === 'Netto');

        $lidl = Deal::where('external_id', '1')->sole();
        $this->assertSame('marktguru', $lidl->source);
        $this->assertSame(Retailer::Lidl, $lidl->retailer);
        $this->assertSame('Product 1', $lidl->title);
        $this->assertSame('Brand 1', $lidl->brand);
        $this->assertSame('1.99', $lidl->price);
        $this->assertSame('2.99', $lidl->old_price);
        $this->assertSame(33, $lidl->discount_percent);
        $this->assertSame('2026-10-31', $lidl->valid_to->toDateString());
        $this->assertSame('Lidl', $lidl->raw['advertisers'][0]['name']);
        $this->assertSame(['10115'], $lidl->postalCodes()->pluck('postal_code')->all());
    }

    public function test_stored_offers_get_a_category_and_a_unit_price(): void
    {
        $this->seed(CategorySeeder::class);
        $gouda = array_replace($this->offer(1, 'Lidl', 2.00), ['product' => ['name' => 'Gouda jung 400 g'], 'brand' => null]);
        $this->catalogue = ['Lidl' => [$gouda]];

        $this->sync('10115');

        $deal = Deal::with('category')->sole();
        $this->assertSame('groceries-dairy', $deal->category->slug);
        $this->assertSame('5.00', $deal->unit_price);
        $this->assertSame('kg', $deal->unit_price_unit);
    }

    public function test_syncing_again_updates_rows_instead_of_duplicating_them(): void
    {
        $this->catalogue = ['Lidl' => [$this->offer(1, 'Lidl', 1.99)]];
        $this->sync('10115');

        $this->catalogue = ['Lidl' => [$this->offer(1, 'Lidl', 1.49)]];
        $this->sync('10115');

        $this->assertSame(1, Deal::count());
        $this->assertSame(1, DealPostalCode::count());
        $this->assertSame('1.49', Deal::sole()->price);
    }

    public function test_an_offer_seen_in_two_postal_codes_is_stored_once_and_linked_to_both(): void
    {
        $this->catalogue = ['Lidl' => [$this->offer(1, 'Lidl', 1.99)]];

        $this->sync('10115');
        $this->sync('80331');

        $this->assertSame(1, Deal::count());
        $this->assertEqualsCanonicalizing(['10115', '80331'], Deal::sole()->postalCodes()->pluck('postal_code')->all());
    }

    public function test_offers_no_longer_listed_are_unlinked_and_deleted_once_no_postal_code_has_them(): void
    {
        $this->catalogue = ['Lidl' => [$this->offer(1, 'Lidl', 1.99), $this->offer(2, 'Lidl', 2.99)]];
        $this->sync('10115');
        $this->sync('80331');

        Carbon::setTestNow(now()->addMinute());
        $this->catalogue = ['Lidl' => [$this->offer(1, 'Lidl', 1.99)]];
        $this->sync('10115');

        $this->assertEqualsCanonicalizing(['10115', '80331'], Deal::where('external_id', '1')->sole()->postalCodes()->pluck('postal_code')->all());
        $this->assertSame(['80331'], Deal::where('external_id', '2')->sole()->postalCodes()->pluck('postal_code')->all());

        Carbon::setTestNow(now()->addMinute());
        $this->sync('80331');

        $this->assertDatabaseMissing('deals', ['external_id' => '2']);
        Carbon::setTestNow();
    }

    public function test_offers_link_to_marktguru_and_to_the_retailers_page_when_it_is_safe(): void
    {
        $this->catalogue = [
            'Kaufland' => [
                [...$this->offer(1, 'Kaufland', 1.99), 'externalUrl' => 'https://filiale.kaufland.de/angebote/uebersicht.html?kloffer-articleID=02982122'],
                [...$this->offer(2, 'Kaufland', 2.49), 'externalUrl' => 'javascript:alert(1)'],
            ],
            'Lidl' => [[...$this->offer(3, 'Lidl', 0.99), 'externalUrl' => null]],
        ];

        $this->sync('10115');

        $this->assertSame(
            ['1' => 'https://filiale.kaufland.de/angebote/uebersicht.html?kloffer-articleID=02982122', '2' => null, '3' => null],
            Deal::query()->orderBy('external_id')->pluck('external_url', 'external_id')->all(),
        );
        $this->assertSame(
            ['1' => 'https://www.marktguru.de/offers/1', '2' => 'https://www.marktguru.de/offers/2', '3' => 'https://www.marktguru.de/offers/3'],
            Deal::query()->orderBy('external_id')->pluck('source_url', 'external_id')->all(),
        );
    }

    public function test_every_page_is_fetched(): void
    {
        $this->catalogue = ['Lidl' => array_map(
            fn (int $id): array => $this->offer($id, 'Lidl', 1.00),
            range(1, DealSyncService::PAGE_SIZE + 30),
        )];

        $this->assertSame(DealSyncService::PAGE_SIZE + 30, $this->sync('10115'));
        Http::assertSent(fn (Request $request): bool => ($request['q'] ?? null) === 'Lidl' && (int) $request['offset'] === DealSyncService::PAGE_SIZE);
    }

    public function test_a_failed_fetch_leaves_stored_offers_untouched(): void
    {
        $existing = Deal::factory()->availableIn('10115')->create();
        $this->failWithStatus = 500;

        try {
            $this->sync('10115');
            $this->fail('Expected DealSourceUnavailable.');
        } catch (DealSourceUnavailable) {
        }

        $this->assertModelExists($existing);
        $this->assertSame(1, DealPostalCode::count());
    }

    public function test_aldi_nord_offers_fill_in_where_marktguru_has_no_aldi(): void
    {
        Cache::flush();
        $this->catalogue = ['Lidl' => [$this->offer(1, 'Lidl', 1.99)]];
        $this->aldiNordPages = [
            'angebote.html' => [$this->aldiNordProduct('287', 1.39, 2.49), [...$this->aldiNordProduct('300', 2.00), 'isAvailable' => false]],
            'angebote-vorschau.html' => [$this->aldiNordProduct('287', 1.29, from: '2026-11-09')],
        ];

        $this->assertSame(3, $this->sync('13439'));

        $deal = Deal::where('external_id', '287:2026-11-02')->sole();
        $this->assertSame('aldi_nord', $deal->source);
        $this->assertSame(Retailer::Aldi, $deal->retailer);
        $this->assertSame('Aldi product 287', $deal->title);
        $this->assertSame('Verschiedene Sorten Im Aufsteller', $deal->description);
        $this->assertSame('1.39', $deal->price);
        $this->assertSame(44, $deal->discount_percent);
        $this->assertSame('7.72', $deal->unit_price);
        $this->assertSame('kg', $deal->unit_price_unit);
        $this->assertSame('https://aldi-nord.test/produkt/aldi-product-287.html', $deal->external_url);
        $this->assertNull($deal->source_url);
        $this->assertSame(['13439'], $deal->postalCodes()->pluck('postal_code')->all());
        $this->assertNull(Deal::where('external_id', '287:2026-11-09')->sole()->old_price);
        $this->assertDatabaseMissing('deals', ['external_id' => '300:2026-11-02']);

        $this->sync('20095');
        Http::assertSentCount(2 * count(Retailer::cases()) + count(AldiNordOfferFeed::PAGES));
    }

    public function test_aldi_nord_offers_are_left_out_where_marktguru_has_aldi_sued(): void
    {
        Cache::flush();
        $this->aldiNordPages = ['angebote.html' => [$this->aldiNordProduct('287', 1.39)]];
        $this->sync('80331');
        $this->assertSame(1, Deal::where('source', 'aldi_nord')->count());

        Carbon::setTestNow(now()->addMinute());
        $this->catalogue = ['Aldi' => [$this->offer(2, 'ALDI SÜD', 0.89)]];
        $this->sync('80331');
        Carbon::setTestNow();

        $this->assertSame(['marktguru'], Deal::query()->pluck('source')->all());
    }

    public function test_a_failing_aldi_nord_page_keeps_its_offers_and_still_syncs_marktguru(): void
    {
        Cache::flush();
        $this->aldiNordPages = ['angebote.html' => [$this->aldiNordProduct('287', 1.39)]];
        $this->sync('13439');

        Cache::flush();
        Carbon::setTestNow(now()->addMinute());
        $this->aldiNordFailsWithStatus = 503;
        $this->catalogue = ['Lidl' => [$this->offer(1, 'Lidl', 1.99)]];
        $this->assertSame(1, $this->sync('13439'));
        Carbon::setTestNow();

        $this->assertEqualsCanonicalizing(['aldi_nord', 'marktguru'], Deal::query()->pluck('source')->all());
        $this->assertSame(2, DealPostalCode::count());
    }

    public function test_expired_offers_are_pruned(): void
    {
        $expired = Deal::factory()->availableIn('10115')->expired()->create();
        $current = Deal::factory()->availableIn('10115')->create();
        $openEnded = Deal::factory()->availableIn('10115')->create(['valid_to' => null]);

        $this->assertSame(1, app(DealSyncService::class)->pruneExpired());

        $this->assertModelMissing($expired);
        $this->assertModelExists($current);
        $this->assertModelExists($openEnded);
        $this->assertSame(2, DealPostalCode::count());
    }

    public function test_postal_codes_in_use_are_distinct(): void
    {
        User::factory()->withPostalCode('10115')->count(2)->create();
        User::factory()->withPostalCode('80331')->create();
        User::factory()->create();

        $this->assertSame(['10115', '80331'], app(DealSyncService::class)->postalCodesInUse()->all());
    }
}
