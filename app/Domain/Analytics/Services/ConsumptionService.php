<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\ConsumptionReportFilters;
use App\Domain\Analytics\Data\ConsumptionScope;
use App\Domain\Products\Support\ProductQuantity;
use App\Enums\ExpenseType;
use App\Enums\LineItemType;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ConsumptionService
{
    /**
     * Full consumption report: monthly variable + contract trend for the year, top items
     * by quantity and spend, and the vendor leaderboard for the selected range.
     *
     * @return array<string, mixed>
     */
    public function report(int $userId, ConsumptionReportFilters $filters, ?ConsumptionScope $scope = null): array
    {
        $scope ??= ConsumptionScope::default();

        $contractSeries = $this->monthlyContractSeries($userId, $filters->year, $filters->categoryId);

        $monthlyTrend = array_map(function (array $row) use ($contractSeries): array {
            foreach ($contractSeries as $series) {
                $row[$series['key']] = $series['monthly'][$row['month']] ?? 0.0;
            }

            return $row;
        }, $this->monthlySpendTrend($userId, $filters->year, $filters->categoryId, $scope));

        $topItems = fn (string $metric): Collection => $this->topItems(
            $userId, $metric, $filters->startDate, $filters->endDate, $filters->categoryId, $filters->limit, $scope
        );

        return [
            'period' => $filters->period,
            'start' => $filters->startDate,
            'end' => $filters->endDate,
            'limit' => $filters->limit,
            'metric' => $filters->metric,
            'year' => $filters->year,
            'include_business' => $scope->includeBusiness,
            'excluded_category_ids' => $scope->excludedCategoryIds,
            'monthly_trend' => $monthlyTrend,
            'contract_series' => array_map(
                fn (array $series): array => ['key' => $series['key'], 'label' => $series['label']],
                $contractSeries
            ),
            'items' => $topItems($filters->metric),
            'top_by_quantity' => $topItems('quantity'),
            'top_by_spend' => $topItems('spend'),
            'vendors' => $this->vendorLeaderboard(
                $userId, $filters->startDate, $filters->endDate, $filters->limit
            ),
        ];
    }

    /**
     * What the user consumes most, ranked by how often it was bought
     * ('quantity') or by spend. Lines are grouped by their product's general
     * name, then by product, then by the raw receipt name for unmatched lines.
     * Only product lines in the consumption scope count.
     *
     * @param  string  $metric  'quantity' or 'spend'
     * @return Collection<int, object>
     */
    public function topItems(
        int $userId,
        string $metric = 'quantity',
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $categoryId = null,
        int $limit = 10,
        ?ConsumptionScope $scope = null,
    ): Collection {
        $query = $this->consumedLines($userId, $scope ?? ConsumptionScope::default());

        $this->applyReceiptDateRange($query, $startDate, $endDate);
        $this->applyCategoryFilter($query, $categoryId);

        return $this->rank($this->aggregate($query), $metric, $limit);
    }

    /**
     * Consumed items whose receipt name, product or general name matches a
     * search term, grouped and ranked like topItems(). Used by the assistant's
     * item_search intent.
     *
     * @param  string  $metric  'quantity' or 'spend'
     * @return Collection<int, object>
     */
    public function searchItems(
        int $userId,
        string $term,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $categoryId = null,
        string $metric = 'quantity',
        int $limit = 10,
        ?ConsumptionScope $scope = null,
    ): Collection {
        $like = '%'.$term.'%';
        $query = $this->consumedLines($userId, $scope ?? ConsumptionScope::default())
            ->where(fn (Builder $query) => $query
                ->where('receipt_items.name', 'like', $like)
                ->orWhere('products.name', 'like', $like)
                ->orWhere('consumable_groups.name', 'like', $like));

        $this->applyReceiptDateRange($query, $startDate, $endDate);
        $this->applyCategoryFilter($query, $categoryId);

        return $this->rank($this->aggregate($query), $metric, $limit);
    }

    /**
     * Categories (parent or sub) whose name matches a term, each with rolled-up
     * spend (a parent includes its subcategories) and the number of line items.
     *
     * @return Collection<int, object>
     */
    public function searchCategories(int $userId, string $term): Collection
    {
        return Category::query()
            ->where('name', 'like', '%'.$term.'%')
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) use ($userId): object {
                $ids = $category->isParent()
                    ? $category->subcategories->pluck('id')->push($category->id)->all()
                    : [$category->id];

                $agg = ReceiptItem::query()
                    ->join('receipts', fn ($join) => $join->on('receipt_items.receipt_id', '=', 'receipts.id')->whereNull('receipts.duplicate_of_id'))
                    ->where('receipts.user_id', $userId)
                    ->whereIn('receipt_items.category_id', $ids)
                    ->selectRaw('COALESCE(SUM(receipt_items.total), 0) as total_spend')
                    ->selectRaw('COUNT(*) as item_count')
                    ->first();

                return (object) [
                    'category' => $category->name,
                    'is_parent' => $category->isParent(),
                    'total_spend' => round((float) $agg->total_spend, 2),
                    'item_count' => (int) $agg->item_count,
                ];
            });
    }

    /**
     * Vendors ranked by total spend, with receipt counts.
     *
     * @return Collection<int, object>
     */
    public function vendorLeaderboard(
        int $userId,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 10,
    ): Collection {
        $query = Receipt::query()
            ->expenses()
            ->where('user_id', $userId)
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->groupBy('vendor')
            ->selectRaw('vendor')
            ->selectRaw('COUNT(*) as receipt_count')
            ->selectRaw('SUM(total_amount) as total_spent')
            ->orderByDesc('total_spent')
            ->limit($limit);

        $this->applyReceiptDateRange($query, $startDate, $endDate, qualified: false);

        return $query->get();
    }

    /**
     * Spend per calendar month for a given year, optionally scoped to a category
     * (a parent category includes its subcategories). Always returns 12 entries,
     * zero-filling months without spend. Business receipts and excluded
     * categories follow the scope; every line type counts, since deposits and
     * discounts are real money.
     *
     * @return array<int, array{month: int, label: string, total: float}>
     */
    public function monthlySpendTrend(int $userId, int $year, ?int $categoryId = null, ?ConsumptionScope $scope = null): array
    {
        $start = sprintf('%04d-01-01 00:00:00', $year);
        $end = sprintf('%04d-12-31 23:59:59', $year);

        $query = ReceiptItem::query()
            ->join('receipts', fn ($join) => $join->on('receipt_items.receipt_id', '=', 'receipts.id')->whereNull('receipts.duplicate_of_id'))
            ->where('receipts.user_id', $userId)
            ->whereBetween('receipts.receipt_date', [$start, $end])
            ->select(['receipt_items.total', 'receipts.receipt_date']);

        $this->applyCategoryFilter($query, $categoryId);
        $this->applyScope($query, $scope ?? ConsumptionScope::default());

        $totals = array_fill(1, 12, 0.0);

        $query->get()->each(function (object $row) use (&$totals): void {
            $month = (int) Carbon::parse($row->receipt_date)->format('n');
            $totals[$month] += (float) $row->total;
        });

        $trend = [];
        foreach ($totals as $month => $total) {
            $trend[] = [
                'month' => $month,
                'label' => Carbon::create($year, $month, 1)->format('M'),
                'total' => round($total, 2),
            ];
        }

        return $trend;
    }

    /**
     * Fixed contract cost (normalized to a monthly equivalent) per calendar month
     * for a given year, optionally scoped to a category. A contract contributes to
     * a month when its billing window (start_date/end_date) overlaps that month.
     * Always returns 12 entries.
     *
     * @return array<int, array{month: int, label: string, total: float}>
     */
    public function monthlyContractTrend(int $userId, int $year, ?int $categoryId = null): array
    {
        $contracts = $this->contractsForTrend($userId, $categoryId);

        $trend = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
            $trend[] = [
                'month' => $month,
                'label' => $monthStart->format('M'),
                'total' => $this->contractTotalForMonth($contracts, $monthStart),
            ];
        }

        return $trend;
    }

    /**
     * Fixed contract cost per month, split into one series per contract category.
     * When a single category is selected the result is a single combined series.
     *
     * @return array<int, array{key: string, label: string, monthly: array<int, float>}>
     */
    public function monthlyContractSeries(int $userId, int $year, ?int $categoryId = null): array
    {
        $contracts = $this->contractsForTrend($userId, $categoryId);

        if ($contracts->isEmpty()) {
            return [];
        }

        $groups = $categoryId
            ? collect(['contracts' => ['label' => 'Contracts (fixed)', 'contracts' => $contracts]])
            : $contracts->groupBy(fn (Contract $contract): string => $contract->category?->name ?? 'Uncategorized')
                ->map(fn (Collection $group, string $name): array => ['label' => $name, 'contracts' => $group]);

        return $groups->map(function (array $group, string $key) use ($year): array {
            $monthly = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
                $monthly[$month] = $this->contractTotalForMonth($group['contracts'], $monthStart);
            }

            return [
                'key' => is_numeric($key) ? "cat_{$key}" : $key,
                'label' => $group['label'],
                'monthly' => $monthly,
            ];
        })->values()->all();
    }

    /**
     * Contracts eligible for trend display, scoped to a category (incl. subcategories).
     *
     * @return Collection<int, Contract>
     */
    private function contractsForTrend(int $userId, ?int $categoryId): Collection
    {
        $categoryIds = $this->resolveCategoryIds($categoryId);

        return Contract::query()
            ->with('category:id,name')
            ->where('user_id', $userId)
            ->when($categoryIds !== null, fn (Builder $query) => $query->whereIn('category_id', $categoryIds))
            ->get();
    }

    /**
     * Sum of normalized monthly cost for contracts whose billing window covers the month.
     *
     * @param  Collection<int, Contract>  $contracts
     */
    private function contractTotalForMonth(Collection $contracts, Carbon $monthStart): float
    {
        $monthEnd = $monthStart->copy()->endOfMonth();

        return round($contracts
            ->filter(function (Contract $contract) use ($monthStart, $monthEnd): bool {
                if ($contract->start_date && $contract->start_date->gt($monthEnd)) {
                    return false;
                }

                return ! ($contract->end_date && $contract->end_date->lt($monthStart));
            })
            ->sum(fn (Contract $contract): float => $contract->projectedMonthlyAmount()), 2);
    }

    /**
     * The user's consumed lines (product lines on non-duplicate receipts in
     * the scope) with their product and general name.
     *
     * @return Builder<ReceiptItem>
     */
    private function consumedLines(int $userId, ConsumptionScope $scope): Builder
    {
        $query = ReceiptItem::query()
            ->join('receipts', fn ($join) => $join->on('receipt_items.receipt_id', '=', 'receipts.id')->whereNull('receipts.duplicate_of_id'))
            ->leftJoin('categories', 'receipt_items.category_id', '=', 'categories.id')
            ->leftJoin('products', 'receipt_items.product_id', '=', 'products.id')
            ->leftJoin('consumable_groups', 'products.consumable_group_id', '=', 'consumable_groups.id')
            ->where('receipts.user_id', $userId)
            ->where('receipt_items.line_type', LineItemType::Product->value)
            ->whereNotNull('receipt_items.name')
            ->where('receipt_items.name', '!=', '');

        $this->applyScope($query, $scope);

        return $query;
    }

    /**
     * Personal receipts only unless business ones are included, and none of
     * the excluded categories (or their subcategories).
     */
    private function applyScope(Builder $query, ConsumptionScope $scope): void
    {
        if (! $scope->includeBusiness) {
            $query->where('receipts.expense_type', ExpenseType::Personal->value);
        }

        $excluded = collect($scope->excludedCategoryIds)
            ->flatMap(fn (int $id): array => $this->resolveCategoryIds($id) ?? [])
            ->unique()
            ->values()
            ->all();

        if ($excluded === []) {
            return;
        }

        foreach (['receipt_items.category_id', 'receipt_items.subcategory_id'] as $column) {
            $query->where(fn (Builder $query) => $query->whereNull($column)->orWhereNotIn($column, $excluded));
        }
    }

    /**
     * Sum the lines per general name (else product, else receipt name),
     * converting amounts into the general name's unit.
     *
     * @param  Builder<ReceiptItem>  $query
     * @return Collection<int, object>
     */
    private function aggregate(Builder $query): Collection
    {
        $rows = $query
            ->groupBy(
                'receipt_items.product_id', 'receipt_items.name', 'categories.name',
                'products.name', 'products.unit', 'products.size',
                'consumable_groups.id', 'consumable_groups.name', 'consumable_groups.unit',
            )
            ->selectRaw('receipt_items.product_id, receipt_items.name as line_name, categories.name as category_name')
            ->selectRaw('products.name as product_name, products.unit as product_unit, products.size as product_size')
            ->selectRaw('consumable_groups.id as group_id, consumable_groups.name as group_name, consumable_groups.unit as group_unit')
            ->selectRaw('SUM(receipt_items.quantity) as total_quantity')
            ->selectRaw('SUM(receipt_items.total) as total_spend')
            ->selectRaw('COUNT(*) as purchase_count')
            ->toBase()
            ->get()
            ->sortByDesc(fn (object $row): float => (float) $row->total_spend);

        $items = [];

        foreach ($rows as $row) {
            $key = match (true) {
                $row->group_id !== null => 'group:'.$row->group_id,
                $row->product_id !== null => 'product:'.$row->product_id,
                default => 'name:'.mb_strtolower($row->line_name),
            };

            $item = $items[$key] ??= (object) [
                'key' => $key,
                'item_name' => $row->group_name ?? $row->product_name ?? $row->line_name,
                'category_name' => $row->category_name,
                'total_quantity' => 0.0,
                'total_spend' => 0.0,
                'purchase_count' => 0,
                'amount' => null,
                'unit' => ProductQuantity::baseUnit($row->group_unit),
                'unmeasured_count' => 0,
                'variants' => [],
            ];

            $quantity = (float) $row->total_quantity;
            $purchases = (int) $row->purchase_count;
            $item->total_quantity += $quantity;
            $item->total_spend += (float) $row->total_spend;
            $item->purchase_count += $purchases;
            $item->category_name ??= $row->category_name;

            $variant = $row->product_name ?? $row->line_name;
            if (! in_array($variant, $item->variants, true)) {
                $item->variants[] = $variant;
            }

            $amount = $row->product_id !== null
                ? ProductQuantity::total($row->product_unit, $row->product_size, $quantity)
                : null;
            $item->unit ??= $amount['unit'] ?? null;

            if ($amount !== null && $amount['unit'] === $item->unit) {
                $item->amount = ($item->amount ?? 0.0) + $amount['amount'];
            } else {
                $item->unmeasured_count += $purchases;
            }
        }

        return collect(array_values($items))->map(function (object $item): object {
            $item->total_quantity = round($item->total_quantity, 3);
            $item->total_spend = round($item->total_spend, 2);
            $item->amount = $item->amount === null ? null : round($item->amount, 3);
            $item->unit = $item->amount === null ? null : $item->unit;

            return $item;
        });
    }

    /**
     * Rank by spend, or by how often the item was bought, then how many units,
     * then spend: amounts in different units cannot be compared.
     *
     * @param  Collection<int, object>  $items
     * @return Collection<int, object>
     */
    private function rank(Collection $items, string $metric, int $limit): Collection
    {
        $sorted = $metric === 'spend'
            ? $items->sortByDesc('total_spend')
            : $items->sortBy([
                fn (object $a, object $b): int => $b->purchase_count <=> $a->purchase_count,
                fn (object $a, object $b): int => $b->total_quantity <=> $a->total_quantity,
                fn (object $a, object $b): int => $b->total_spend <=> $a->total_spend,
            ]);

        return $sorted->take($limit)->values();
    }

    /**
     * Constrain a query by receipt_date. When $qualified, the column is on the
     * joined receipts table; otherwise it is on the base receipts query.
     */
    private function applyReceiptDateRange(Builder $query, ?string $startDate, ?string $endDate, bool $qualified = true): void
    {
        $column = $qualified ? 'receipts.receipt_date' : 'receipt_date';

        if ($startDate) {
            $query->where($column, '>=', $startDate);
        }

        if ($endDate) {
            $query->where($column, '<=', $endDate.' 23:59:59');
        }
    }

    /**
     * Filter receipt items by category. A parent category includes its subcategories.
     */
    private function applyCategoryFilter(Builder $query, ?int $categoryId): void
    {
        $ids = $this->resolveCategoryIds($categoryId);

        if ($ids !== null) {
            $query->whereIn('receipt_items.category_id', $ids);
        }
    }

    /**
     * Expand a category id into itself plus any subcategories. Returns null when
     * no (or an unknown) category is given, meaning "do not filter".
     *
     * @return array<int, int>|null
     */
    private function resolveCategoryIds(?int $categoryId): ?array
    {
        if (! $categoryId) {
            return null;
        }

        $category = Category::find($categoryId);

        if (! $category) {
            return null;
        }

        return $category->isParent()
            ? $category->subcategories->pluck('id')->push($categoryId)->all()
            : [$categoryId];
    }
}
