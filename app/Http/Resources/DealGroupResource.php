<?php

namespace App\Http\Resources;

use App\Domain\Deals\Data\DealGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DealGroup */
class DealGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'category_id' => $this->categoryId,
            'name' => $this->name,
            'total' => $this->total,
            'offers' => DealResource::collection($this->deals)->resolve($request),
        ];
    }
}
