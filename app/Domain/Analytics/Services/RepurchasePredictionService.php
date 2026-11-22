<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumedLine;
use App\Domain\Products\Support\ProductQuantity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Likely to buy soon", from the user's own rhythm and without AI: things
 * bought at regular intervals whose next purchase falls due within a week.
 */
class RepurchasePredictionService
{
    public const HORIZON_DAYS = 7;

    /**
     * Shopping days needed before a rhythm is believed.
     */
    public const MIN_DAYS_BOUGHT = 4;

    /**
     * How far the typical gap may stray from the median gap, as a share of it.
     */
    public const MAX_IRREGULARITY = 0.5;

    /**
     * @param  Collection<int, ConsumedLine>  $lines  oldest first
     * @return array<int, array<string, mixed>> soonest first
     */
    public function dueSoon(Collection $lines, CarbonImmutable $today): array
    {
        return $lines
            ->filter(fn (ConsumedLine $line): bool => $line->total > 0)
            ->groupBy(fn (ConsumedLine $line): string => $line->key)
            ->map(fn (Collection $purchases): ?array => $this->predict($purchases, $today))
            ->filter()
            ->sortBy([['days_until', 'asc'], ['estimate', 'desc']])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ConsumedLine>  $purchases
     * @return array<string, mixed>|null
     */
    private function predict(Collection $purchases, CarbonImmutable $today): ?array
    {
        $byDay = $purchases->groupBy(fn (ConsumedLine $line): string => $line->date->toDateString());

        if ($byDay->count() < self::MIN_DAYS_BOUGHT) {
            return null;
        }

        $days = $byDay->keys()->map(fn (string $day): CarbonImmutable => CarbonImmutable::parse($day));
        $gaps = $days->skip(1)->values()->map(fn (CarbonImmutable $day, int $index): int => (int) $days[$index]->diffInDays($day));
        $usualGap = (float) $gaps->median();
        $straying = (float) $gaps->map(fn (int $gap): float => abs($gap - $usualGap))->median();

        if ($straying > $usualGap * self::MAX_IRREGULARITY) {
            return null;
        }

        $everyDays = (int) round($usualGap);
        $due = $days->last()->addDays($everyDays);
        $daysUntil = (int) $today->diffInDays($due, false);

        // More than a whole interval late reads as stopped, not as due.
        if ($daysUntil > self::HORIZON_DAYS || $daysUntil < -$everyDays) {
            return null;
        }

        return [
            'key' => $purchases->first()->key,
            'label' => $purchases->last()->label,
            'every_days' => $everyDays,
            'last_bought' => $days->last()->toDateString(),
            'due' => $due->toDateString(),
            'days_until' => $daysUntil,
            'amount_label' => $this->usualAmount($byDay),
            'estimate' => round((float) $byDay->map(fn (Collection $day): float => $day->sum(fn (ConsumedLine $line): float => $line->total))->median(), 2),
        ];
    }

    /**
     * @param  Collection<string, Collection<int, ConsumedLine>>  $byDay
     */
    private function usualAmount(Collection $byDay): ?string
    {
        $amounts = $byDay->flatten(1)->map(fn (ConsumedLine $line): ?array => $line->amount());

        if ($amounts->containsStrict(null) || $amounts->pluck('unit')->unique()->count() !== 1) {
            return null;
        }

        $usual = (float) $byDay->map(fn (Collection $day): float => $day->sum(fn (ConsumedLine $line): float => $line->amount()['amount']))->median();

        return ProductQuantity::label($usual, $amounts->first()['unit']);
    }
}
