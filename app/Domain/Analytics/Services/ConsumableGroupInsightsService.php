<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumptionScope;
use App\Domain\Products\Support\ProductQuantity;
use App\Enums\ExpenseType;
use App\Enums\LineItemType;
use App\Models\ConsumableGroup;
use App\Models\ReceiptItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything about one general name ("Milk"): how much and how often it is
 * bought, what a litre or kilo costs and where it is cheapest, the products it
 * groups and the recent purchases.
 */
class ConsumableGroupInsightsService
{
    /**
     * How many months the monthly chart covers.
     */
    public const MONTHS = 12;

    /**
     * How many recent purchases the page lists.
     */
    public const RECENT_PURCHASES = 25;

    public function __construct(private ConsumptionService $consumptionService) {}

    /**
     * @return array<string, mixed>
     */
    public function detail(ConsumableGroup $group, ?ConsumptionScope $scope = null): array
    {
        $lines = $this->lines($group, $scope ?? ConsumptionScope::default());
        $unit = $group->unit ?? $lines->pluck('unit')->filter()->first();
        $measured = $lines->filter(fn (object $line): bool => $line->amount !== null && $line->unit === $unit);

        return [
            'group' => ['id' => $group->id, 'name' => $group->name, 'unit' => $unit],
            'totals' => [
                'amount' => $measured->isEmpty() ? null : round($measured->sum('amount'), 3),
                'unit' => $unit,
                'unmeasured_count' => $lines->count() - $measured->count(),
                'spend' => round($lines->sum('total'), 2),
                'purchases' => $lines->count(),
                'first_bought' => $lines->min('date'),
                'last_bought' => $lines->max('date'),
                'days_between_purchases' => $this->daysBetweenPurchases($lines),
                'price_per_unit' => $this->pricePerUnit($measured),
            ],
            'monthly' => $this->monthly($lines, $measured),
            'vendors' => $this->vendors($lines, $measured),
            'products' => $this->products($group, $lines, $unit),
            'purchases' => $this->recentPurchases($group, $scope ?? ConsumptionScope::default()),
            'other_groups' => ConsumableGroup::query()
                ->where('user_id', $group->user_id)
                ->whereKeyNot($group->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ConsumableGroup $other): array => ['id' => $other->id, 'name' => $other->name])
                ->all(),
        ];
    }

    /**
     * The group's consumed lines, each with its amount in the base unit.
     *
     * @return Collection<int, object>
     */
    private function lines(ConsumableGroup $group, ConsumptionScope $scope): Collection
    {
        return $this->consumptionService->consumedLines($group->user_id, $scope)
            ->where('products.consumable_group_id', $group->id)
            ->orderBy('receipts.receipt_date')
            ->get([
                'receipt_items.id', 'receipt_items.quantity', 'receipt_items.total', 'receipt_items.product_id',
                'receipts.receipt_date', 'receipts.vendor', 'products.unit as product_unit', 'products.size as product_size',
            ])
            ->map(function (ReceiptItem $item): object {
                $amount = ProductQuantity::total($item->product_unit, $item->product_size, (float) $item->quantity);

                return (object) [
                    'product_id' => $item->product_id,
                    'date' => Carbon::parse($item->receipt_date)->toDateString(),
                    'vendor' => $item->vendor ?: 'Unknown shop',
                    'total' => (float) $item->total,
                    'amount' => $amount['amount'] ?? null,
                    'unit' => $amount['unit'] ?? null,
                ];
            });
    }

    /**
     * Average days between shopping days on which the item was bought.
     *
     * @param  Collection<int, object>  $lines
     */
    private function daysBetweenPurchases(Collection $lines): ?float
    {
        $days = $lines->pluck('date')->unique()->sort()->values();

        if ($days->count() < 2) {
            return null;
        }

        $span = Carbon::parse($days->first())->diffInDays(Carbon::parse($days->last()));

        return round($span / ($days->count() - 1), 1);
    }

    /**
     * @param  Collection<int, object>  $measured
     */
    private function pricePerUnit(Collection $measured): ?float
    {
        $amount = $measured->sum('amount');

        return $amount > 0 ? round($measured->sum('total') / $amount, 2) : null;
    }

    /**
     * The last MONTHS calendar months, oldest first, zero-filled.
     *
     * @param  Collection<int, object>  $lines
     * @param  Collection<int, object>  $measured
     * @return array<int, array{month: string, label: string, amount: ?float, spend: float, purchases: int}>
     */
    private function monthly(Collection $lines, Collection $measured): array
    {
        $months = [];
        $first = Carbon::today()->startOfMonth()->subMonths(self::MONTHS - 1);

        for ($i = 0; $i < self::MONTHS; $i++) {
            $month = $first->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $inMonth = fn (object $line): bool => str_starts_with($line->date, $key);
            $measuredInMonth = $measured->filter($inMonth);

            $months[] = [
                'month' => $key,
                'label' => $month->format('M y'),
                'amount' => $measuredInMonth->isEmpty() ? null : round($measuredInMonth->sum('amount'), 3),
                'spend' => round($lines->filter($inMonth)->sum('total'), 2),
                'purchases' => $lines->filter($inMonth)->count(),
            ];
        }

        return $months;
    }

    /**
     * Shops, cheapest per unit first (shops without a known unit price last).
     *
     * @param  Collection<int, object>  $lines
     * @param  Collection<int, object>  $measured
     * @return array<int, array{vendor: string, purchases: int, spend: float, price_per_unit: ?float}>
     */
    private function vendors(Collection $lines, Collection $measured): array
    {
        return $lines->groupBy('vendor')
            ->map(fn (Collection $vendorLines, string $vendor): array => [
                'vendor' => $vendor,
                'purchases' => $vendorLines->count(),
                'spend' => round($vendorLines->sum('total'), 2),
                'price_per_unit' => $this->pricePerUnit($measured->where('vendor', $vendor)),
            ])
            ->sortBy(fn (array $row): float => $row['price_per_unit'] ?? PHP_FLOAT_MAX)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function products(ConsumableGroup $group, Collection $lines, ?string $unit): array
    {
        $byProduct = $lines->groupBy('product_id');

        return $group->products()
            ->orderBy('name')
            ->get(['id', 'name', 'brand', 'unit', 'size'])
            ->map(function ($product) use ($byProduct, $unit): array {
                $productLines = $byProduct->get($product->id, collect());
                $measured = $productLines->filter(fn (object $line): bool => $line->amount !== null && $line->unit === $unit);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'pack' => trim(($product->size ?? '').' '.($product->unit ?? '')) ?: null,
                    'purchases' => $productLines->count(),
                    'amount' => $measured->isEmpty() ? null : round($measured->sum('amount'), 3),
                    'spend' => round($productLines->sum('total'), 2),
                ];
            })
            ->sortByDesc('spend')
            ->values()
            ->all();
    }

    /**
     * Recent lines of the group's products, including ones the user left out,
     * so leaving a line out can be undone.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentPurchases(ConsumableGroup $group, ConsumptionScope $scope): array
    {
        return ReceiptItem::query()
            ->join('receipts', fn ($join) => $join->on('receipt_items.receipt_id', '=', 'receipts.id')->whereNull('receipts.duplicate_of_id'))
            ->join('products', 'receipt_items.product_id', '=', 'products.id')
            ->where('receipts.user_id', $group->user_id)
            ->where('products.consumable_group_id', $group->id)
            ->whereIn('receipt_items.line_type', [LineItemType::Product->value, LineItemType::Excluded->value])
            ->when(! $scope->includeBusiness, fn ($query) => $query->where('receipts.expense_type', ExpenseType::Personal->value))
            ->orderByDesc('receipts.receipt_date')
            ->orderByDesc('receipt_items.id')
            ->limit(self::RECENT_PURCHASES)
            ->get([
                'receipt_items.id', 'receipt_items.name', 'receipt_items.quantity', 'receipt_items.total',
                'receipt_items.line_type', 'receipt_items.receipt_id', 'receipts.receipt_date', 'receipts.vendor',
                'products.name as product_name',
            ])
            ->map(fn (ReceiptItem $item): array => [
                'id' => $item->id,
                'date' => Carbon::parse($item->receipt_date)->toDateString(),
                'vendor' => $item->vendor,
                'name' => $item->name,
                'product_name' => $item->product_name,
                'quantity' => (float) $item->quantity,
                'total' => (float) $item->total,
                'counts' => $item->line_type === LineItemType::Product,
                'receipt_id' => $item->receipt_id,
            ])
            ->all();
    }
}
