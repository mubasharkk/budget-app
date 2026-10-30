<?php

namespace App\Http\Resources;

use App\Domain\Deals\Data\DealSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DealSummary */
class DealSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'match_count' => $this->matchCount,
            'possible_saving' => $this->possibleSaving,
            'best_retailer' => $this->bestRetailer?->value,
            'best_retailer_label' => $this->bestRetailer?->label(),
        ];
    }
}
