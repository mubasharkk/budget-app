<?php

namespace App\Models\Concerns;

use App\Models\Receipt;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A record receipts can be attached to (ReceiptLinkType). Deleting the record
 * only removes the links; the receipts stay.
 */
trait HasReceipts
{
    public static function bootHasReceipts(): void
    {
        static::deleting(function (self $model): void {
            $model->receipts()->detach();
        });
    }

    public function receipts(): MorphToMany
    {
        return $this->morphToMany(Receipt::class, 'linkable', 'receipt_links')->withTimestamps();
    }
}
