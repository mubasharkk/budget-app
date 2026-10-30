<?php

namespace App\Http\Controllers;

use App\Domain\Deals\Data\DealFilters;
use App\Domain\Deals\Data\DealSummary;
use App\Domain\Deals\Services\DealCatalogService;
use App\Domain\Deals\Services\DealRecommendationService;
use App\Http\Requests\DealOffersRequest;
use App\Http\Requests\DealsForYouRequest;
use App\Http\Resources\DealGroupResource;
use App\Http\Resources\DealRecommendationResource;
use App\Http\Resources\DealSummaryResource;
use Illuminate\Http\JsonResponse;

class DealController extends Controller
{
    public function __construct(
        private DealCatalogService $dealCatalogService,
        private DealRecommendationService $dealRecommendationService,
    ) {}

    /**
     * Stored offers for the user's postal code, grouped by category.
     */
    public function offers(DealOffersRequest $request): JsonResponse
    {
        if (($postalCode = $request->user()->postal_code) === null) {
            return $this->postalCodeRequired();
        }

        $groups = $this->dealCatalogService->browse(
            $request->user()->id,
            $postalCode,
            DealFilters::fromArray($request->validated()),
        );

        return response()->json([
            ...$this->meta($postalCode),
            'groups' => DealGroupResource::collection($groups),
        ]);
    }

    /**
     * Offers on things the user buys, with the headline saving numbers.
     */
    public function forYou(DealsForYouRequest $request): JsonResponse
    {
        if (($postalCode = $request->user()->postal_code) === null) {
            return $this->postalCodeRequired();
        }

        $recommendations = $this->dealRecommendationService->forUser($request->user()->id, $postalCode);

        return response()->json([
            ...$this->meta($postalCode),
            'summary' => new DealSummaryResource(DealSummary::fromRecommendations($recommendations)),
            'recommendations' => DealRecommendationResource::collection($recommendations->take($request->limit())),
        ]);
    }

    /**
     * @return array{postal_code: string, synced_at: ?string}
     */
    private function meta(string $postalCode): array
    {
        return [
            'postal_code' => $postalCode,
            'synced_at' => $this->dealCatalogService->lastSyncedAt($postalCode),
        ];
    }

    private function postalCodeRequired(): JsonResponse
    {
        return response()->json([
            'message' => 'Add your postal code to see deals near you.',
            'postal_code_required' => true,
        ], 409);
    }
}
