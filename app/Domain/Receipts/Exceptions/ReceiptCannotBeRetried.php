<?php

namespace App\Domain\Receipts\Exceptions;

use App\Models\Receipt;
use DomainException;

class ReceiptCannotBeRetried extends DomainException
{
    public static function notFailed(Receipt $receipt): self
    {
        return new self("Receipt {$receipt->id} is {$receipt->status}; only failed receipts can be retried.");
    }
}
