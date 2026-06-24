<?php

namespace App\Services;

use App\Models\Category;
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
        if (! $categoryId) {
            return;
        }

        $category = Category::find($categoryId);

        if (! $category) {
            return;
        }

        if ($category->isParent()) {
            $ids = $category->subcategories->pluck('id')->push($categoryId)->all();
            $query->whereIn('receipt_items.category_id', $ids);
        } else {
            $query->where('receipt_items.category_id', $categoryId);
        }
    }
}
