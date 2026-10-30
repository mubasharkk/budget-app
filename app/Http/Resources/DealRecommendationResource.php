<?php

namespace App\Http\Resources;

use App\Domain\Deals\Data\DealRecommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DealRecommendation */
class DealRecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'offer' => (new DealResource($this->deal))->resolve($request),
            'product' => [
                'id' => $this->product->productId,
                'name' => $this->product->name,
                'purchase_count' => $this->product->purchaseCount,
                'last_paid_price' => $this->product->lastPaidPrice,
                'last_vendor' => $this->product->lastVendor,
            ],
            'saving' => $this->saving(),
        ];
    }
}
