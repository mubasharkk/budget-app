<?php

namespace App\Services;

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
     * Most-consumed line items ranked by quantity or spend.
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
    ): Collection {
        $orderColumn = $metric === 'spend' ? 'total_spend' : 'total_quantity';

        $query = ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->leftJoin('categories', 'receipt_items.category_id', '=', 'categories.id')
            ->where('receipts.user_id', $userId)
            ->whereNotNull('receipt_items.name')
            ->where('receipt_items.name', '!=', '')
            ->groupBy('receipt_items.name', 'categories.name')
            ->selectRaw('receipt_items.name as item_name')
            ->selectRaw('categories.name as category_name')
            ->selectRaw('SUM(receipt_items.quantity) as total_quantity')
            ->selectRaw('SUM(receipt_items.total) as total_spend')
            ->selectRaw('COUNT(*) as purchase_count')
            ->orderByDesc($orderColumn)
            ->limit($limit);

        $this->applyReceiptDateRange($query, $startDate, $endDate);
        $this->applyCategoryFilter($query, $categoryId);

        return $query->get()->map(function (object $row): object {
            $row->total_quantity = (float) $row->total_quantity;
            $row->total_spend = round((float) $row->total_spend, 2);
            $row->purchase_count = (int) $row->purchase_count;

            return $row;
        });
    }

    /**
     * Line items whose name matches a search term, grouped by item + category,
     * ranked by quantity or spend. Used by the assistant's item_search intent.
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
    ): Collection {
        $orderColumn = $metric === 'spend' ? 'total_spend' : 'total_quantity';

        $query = ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->leftJoin('categories', 'receipt_items.category_id', '=', 'categories.id')
            ->where('receipts.user_id', $userId)
            ->where('receipt_items.name', 'like', '%'.$term.'%')
            ->groupBy('receipt_items.name', 'categories.name')
            ->selectRaw('receipt_items.name as item_name')
            ->selectRaw('categories.name as category_name')
            ->selectRaw('SUM(receipt_items.quantity) as total_quantity')
            ->selectRaw('SUM(receipt_items.total) as total_spend')
            ->selectRaw('COUNT(*) as purchase_count')
            ->orderByDesc($orderColumn)
            ->limit($limit);

        $this->applyReceiptDateRange($query, $startDate, $endDate);
        $this->applyCategoryFilter($query, $categoryId);

        return $query->get()->map(function (object $row): object {
            $row->total_quantity = (float) $row->total_quantity;
            $row->total_spend = round((float) $row->total_spend, 2);
            $row->purchase_count = (int) $row->purchase_count;

            return $row;
        });
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
                    ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
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
     * zero-filling months without spend.
     *
     * @return array<int, array{month: int, label: string, total: float}>
     */
    public function monthlySpendTrend(int $userId, int $year, ?int $categoryId = null): array
    {
        $start = sprintf('%04d-01-01 00:00:00', $year);
        $end = sprintf('%04d-12-31 23:59:59', $year);

        $query = ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->where('receipts.user_id', $userId)
            ->whereBetween('receipts.receipt_date', [$start, $end])
            ->select(['receipt_items.total', 'receipts.receipt_date']);

        $this->applyCategoryFilter($query, $categoryId);

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
