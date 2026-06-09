<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Receipt */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'vendor' => $this->vendor,
            'currency' => $this->currency,
            'total_amount' => $this->total_amount,
            'receipt_date' => $this->receipt_date?->toIso8601String(),
            'status' => $this->status,
            'error_message' => $this->error_message,
            'file_type' => $this->file_type,
            'mime' => $this->mime,
            'file_url' => $this->when(
                $request->user()?->id === $this->user_id,
                fn (): string => route('receipts.file', $this->id),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
