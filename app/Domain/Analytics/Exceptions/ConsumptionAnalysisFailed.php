<?php

namespace App\Domain\Analytics\Exceptions;

use DomainException;

class ConsumptionAnalysisFailed extends DomainException
{
    public static function nothingConsumed(): self
    {
        return new self('Nothing was consumed in this period, so there is nothing to analyse.');
    }

    public static function aiUnavailable(): self
    {
        return new self('The analysis could not be written right now. Please try again later.');
    }
}
