<?php

namespace App\Http\Resources;

use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Deal */
class DealResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'retailer' => $this->retailer->value,
            'retailer_label' => $this->retailer->label(),
            'category_id' => $this->category_id,
            'title' => $this->title,
            'brand' => $this->brand,
            'description' => $this->description,
            'price' => (float) $this->price,
            'old_price' => $this->old_price !== null ? (float) $this->old_price : null,
            'discount_percent' => $this->discount_percent,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'unit_price_unit' => $this->unit_price_unit,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
        ];
    }
}
