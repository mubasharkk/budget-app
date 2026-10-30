<?php

namespace App\Http\Controllers;

use App\Domain\Deals\Data\DealSummary;
use App\Domain\Deals\Services\DealCatalogService;
use App\Domain\Deals\Services\DealComparisonService;
use App\Domain\Deals\Services\DealRecommendationService;
use App\Domain\Products\Services\PriceIntelligenceService;
use App\Enums\DealSort;
use App\Enums\DealWeek;
use App\Enums\Retailer;
use App\Http\Requests\DealsForYouRequest;
use App\Http\Requests\DealsPageRequest;
use App\Http\Resources\DealComparisonResource;
use App\Http\Resources\DealGroupResource;
use App\Http\Resources\DealRecommendationResource;
use App\Http\Resources\DealSummaryResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function __construct(
        private DealCatalogService $dealCatalogService,
        private DealRecommendationService $dealRecommendationService,
        private DealComparisonService $dealComparisonService,
        private PriceIntelligenceService $priceIntelligenceService,
    ) {}

    /**
     * The Deals page. Only the open tab's data is sent; the other tab's props are
     * optional and fetched by a partial reload when the user switches to it.
     */
    public function index(DealsPageRequest $request): Response
    {
        $userId = $request->user()->id;
        $postalCode = $request->user()->postal_code;
        $filters = $request->filters();
        $offersTab = ! $request->onPricesTab();

        $forPostalCode = fn (Closure $resolve): Closure => fn () => $postalCode !== null ? $resolve() : null;

        return Inertia::render('Deals/Index', [
            'retailers' => Retailer::options(),
            'sortOptions' => DealSort::options(),
            'weekOptions' => DealWeek::options(),
            'tab' => $request->tab(),
            'filters' => $filters->toArray(),
            'priceRange' => $request->priceRange(),
            'syncedAt' => $forPostalCode(fn () => $this->dealCatalogService->lastSyncedAt($postalCode)),
            'availableRetailers' => $this->tabProp($offersTab, $forPostalCode(
                fn () => $this->dealCatalogService->retailersWithOffers($postalCode, $filters->week),
            )),
            'offers' => $this->tabProp($offersTab, $forPostalCode(fn () => DealGroupResource::collection(
                $this->dealCatalogService->browse($userId, $postalCode, $filters),
            )->resolve())),
            'forYou' => $this->tabProp($offersTab, $forPostalCode(fn () => $this->forYouData($userId, $postalCode, DealsForYouRequest::DEFAULT_LIMIT)), deferred: true),
            'comparisons' => $this->tabProp($offersTab, $forPostalCode(fn () => DealComparisonResource::collection(
                $this->dealComparisonService->forUser($userId, $postalCode),
            )->resolve()), deferred: true),
            'prices' => $this->tabProp(! $offersTab, fn () => $this->pricesData($userId, $request->priceRange()), deferred: true),
        ]);
    }

    /**
     * Offers on things the user buys, with the headline saving numbers (dashboard widget).
     */
    public function forYou(DealsForYouRequest $request): JsonResponse
    {
        if (($postalCode = $request->user()->postal_code) === null) {
            return response()->json([
                'message' => 'Add your postal code to see deals near you.',
                'postal_code_required' => true,
            ], 409);
        }

        return response()->json([
            'postal_code' => $postalCode,
            'synced_at' => $this->dealCatalogService->lastSyncedAt($postalCode),
            ...$this->forYouData($request->user()->id, $postalCode, $request->limit()),
        ]);
    }

    /**
     * Props of the open tab load with the page (heavier ones deferred); the closed
     * tab's props load only when a partial reload asks for them.
     */
    private function tabProp(bool $onOpenTab, Closure $resolve, bool $deferred = false): mixed
    {
        if (! $onOpenTab) {
            return Inertia::optional($resolve);
        }

        return $deferred ? Inertia::defer($resolve, 'personal') : $resolve;
    }

    /**
     * @return array{summary: array<string, mixed>, recommendations: array<int, mixed>}
     */
    private function forYouData(int $userId, string $postalCode, int $limit): array
    {
        $recommendations = $this->dealRecommendationService->forUser($userId, $postalCode);

        return [
            'summary' => (new DealSummaryResource(DealSummary::fromRecommendations($recommendations)))->resolve(),
            'recommendations' => DealRecommendationResource::collection($recommendations->take($limit))->resolve(),
        ];
    }

    /**
     * What the user paid versus the cheapest price they have seen.
     *
     * @param  array{start_date: string, end_date: string}  $range
     * @return array<string, mixed>
     */
    private function pricesData(int $userId, array $range): array
    {
        return [
            'savings_opportunities' => $this->priceIntelligenceService->savingsOpportunities(
                $userId, $range['start_date'] ?: null, $range['end_date'] ?: null,
            ),
            'cheapest_vendors' => $this->priceIntelligenceService->cheapestVendors($userId),
            'price_trends' => $this->priceIntelligenceService->priceTrends($userId),
        ];
    }
}
