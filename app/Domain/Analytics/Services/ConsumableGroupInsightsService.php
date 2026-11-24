<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumptionScope;
use App\Domain\Products\Support\ProductQuantity;
use App\Enums\LineItemType;
use App\Models\Category;
use App\Models\ConsumableGroup;
use App\Models\Product;
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

    /**
     * @var array<int, array<int, array<int, int>>> per user: group id => top-level category ids
     */
    private array $topCategoriesByGroup = [];

    /**
     * @var array<int, int|null>|null category id => parent id
     */
    private ?array $parentIds = null;

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
            'other_groups' => $this->groupsInCategories($group, $this->topCategoriesOf($group)),
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
            ->get(['id', 'name', 'brand', 'unit', 'size', 'category_id'])
            ->map(function ($product) use ($byProduct, $unit, $group): array {
                $productLines = $byProduct->get($product->id, collect());
                $measured = $productLines->filter(fn (object $line): bool => $line->amount !== null && $line->unit === $unit);

                $perItem = ProductQuantity::perUnit($product->unit, $product->size);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'pack_size' => filled($product->size) && $perItem !== null ? round($perItem['amount'], 6) : null,
                    'pack_unit' => $perItem['unit'] ?? null,
                    'pack' => trim(($product->size ?? '').' '.($product->unit ?? '')) ?: null,
                    'purchases' => $productLines->count(),
                    'amount' => $measured->isEmpty() ? null : round($measured->sum('amount'), 3),
                    'spend' => round($productLines->sum('total'), 2),
                    'move_suggestions' => array_column($this->groupsInCategories(
                        $group,
                        $product->category_id === null ? [] : [$this->topCategoryOf($product->category_id)],
                    ), 'name'),
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
        $query = ReceiptItem::query()
            ->join('receipts', fn ($join) => $join->on('receipt_items.receipt_id', '=', 'receipts.id')->whereNull('receipts.duplicate_of_id'))
            ->join('products', 'receipt_items.product_id', '=', 'products.id')
            ->where('receipts.user_id', $group->user_id)
            ->where('products.consumable_group_id', $group->id)
            ->whereIn('receipt_items.line_type', [LineItemType::Product->value, LineItemType::Excluded->value])
            ->orderByDesc('receipts.receipt_date')
            ->orderByDesc('receipt_items.id')
            ->limit(self::RECENT_PURCHASES);

        $this->consumptionService->applyScope($query, $scope);

        return $query
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

    /**
     * The user's other general names that share one of $categoryIds (top-level),
     * plus those with no known category; all of them when $categoryIds is empty.
     * Keeps a "Toilet paper" page from offering "Milk" and "Bananas".
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, array{id: int, name: string}>
     */
    private function groupsInCategories(ConsumableGroup $group, array $categoryIds): array
    {
        $categoriesByGroup = $this->topCategoriesByGroup($group->user_id);

        return ConsumableGroup::query()
            ->where('user_id', $group->user_id)
            ->whereKeyNot($group->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(function (ConsumableGroup $other) use ($categoriesByGroup, $categoryIds): bool {
                $otherCategories = $categoriesByGroup[$other->id] ?? [];

                return $categoryIds === [] || $otherCategories === [] || array_intersect($categoryIds, $otherCategories) !== [];
            })
            ->map(fn (ConsumableGroup $other): array => ['id' => $other->id, 'name' => $other->name])
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function topCategoriesOf(ConsumableGroup $group): array
    {
        return $this->topCategoriesByGroup($group->user_id)[$group->id] ?? [];
    }

    /**
     * Top-level categories of each of the user's general names, from their products.
     *
     * @return array<int, array<int, int>>
     */
    private function topCategoriesByGroup(int $userId): array
    {
        return $this->topCategoriesByGroup[$userId] ??= Product::query()
            ->where('user_id', $userId)
            ->whereNotNull('consumable_group_id')
            ->whereNotNull('category_id')
            ->get(['consumable_group_id', 'category_id'])
            ->groupBy('consumable_group_id')
            ->map(fn (Collection $products): array => $products
                ->map(fn (Product $product): int => $this->topCategoryOf($product->category_id))
                ->unique()
                ->values()
                ->all())
            ->all();
    }

    private function topCategoryOf(int $categoryId): int
    {
        $this->parentIds ??= Category::query()->pluck('parent_id', 'id')->all();

        return $this->parentIds[$categoryId] ?? $categoryId;
    }
}
