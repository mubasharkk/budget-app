<?php

namespace App\Domain\Products\Exceptions;

use DomainException;

class PackUnitMismatch extends DomainException
{
    public static function with(string $groupName, string $groupUnit): self
    {
        $unit = match ($groupUnit) {
            'l' => 'litres',
            'kg' => 'kilograms',
            default => 'pieces',
        };

        return new self("{$groupName} is counted in {$unit}. Give the size in {$unit}, or give this another general name.");
    }
}
