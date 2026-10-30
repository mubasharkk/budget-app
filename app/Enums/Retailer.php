<?php

namespace App\Enums;

enum Retailer: string
{
    case Lidl = 'lidl';
    case Aldi = 'aldi';
    case Netto = 'netto';
    case Kaufland = 'kaufland';
    case Edeka = 'edeka';

    public function label(): string
    {
        return match ($this) {
            self::Lidl => 'Lidl',
            self::Aldi => 'Aldi',
            self::Netto => 'Netto',
            self::Kaufland => 'Kaufland',
            self::Edeka => 'Edeka',
        };
    }

    /**
     * Map a source's advertiser name ("ALDI SÜD", "Netto Marken-Discount", "EDEKA") to a supported retailer.
     */
    public static function fromAdvertiserName(?string $advertiserName): ?self
    {
        $normalized = mb_strtolower(trim((string) $advertiserName));

        if ($normalized === '') {
            return null;
        }

        foreach (self::cases() as $retailer) {
            if (str_contains($normalized, $retailer->value)) {
                return $retailer;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $retailer): array => ['value' => $retailer->value, 'label' => $retailer->label()],
            self::cases(),
        );
    }
}
