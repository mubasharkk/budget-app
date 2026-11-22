<?php

namespace App\Domain\Products\Data;

use App\Domain\Products\Support\ProductQuantity;
use Illuminate\Support\Str;

/**
 * What the user corrected about a product: its general name, its pack, or both.
 * A pack is stored in its base unit ("250 g" → size 0.25, unit kg).
 */
readonly class ProductCorrection
{
    public function __construct(
        public ?string $groupName = null,
        public ?string $unit = null,
        public ?string $size = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request input
     */
    public static function fromArray(array $input): self
    {
        $groupName = Str::squish((string) ($input['group_name'] ?? ''));
        $unit = $input['unit'] ?? null;
        $size = $input['size'] ?? null;
        $pack = filled($unit) ? ProductQuantity::perUnit($unit, filled($size) ? (string) $size : null) : null;

        return new self(
            groupName: $groupName === '' ? null : $groupName,
            unit: $pack['unit'] ?? null,
            size: $pack !== null && filled($size) ? rtrim(rtrim(number_format($pack['amount'], 6, '.', ''), '0'), '.') : null,
        );
    }

    public function changesPack(): bool
    {
        return $this->unit !== null;
    }
}
