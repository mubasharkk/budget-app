<?php

namespace App\Http\Resources;

use App\Domain\Deals\Data\DealComparison;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DealComparison */
class DealComparisonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product' => [
                'id' => $this->product->productId,
                'name' => $this->product->name,
                'purchase_count' => $this->product->purchaseCount,
            ],
            'compared_by' => $this->comparesUnitPrices ? 'unit_price' : 'price',
            'price_gap' => $this->priceGap(),
            'offers' => DealResource::collection($this->deals)->resolve($request),
        ];
    }
}
