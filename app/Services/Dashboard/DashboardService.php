<?php

namespace App\Services\Dashboard;

use App\Models\ReceiptItem;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get the most bought items for chart data
     *
     * @param int $userId
     * @param string|null $startDate
     * @param string|null $endDate
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMostBoughtItems(int $userId, ?string $startDate = null, ?string $endDate = null, int $limit = 10)
    {
        $query = ReceiptItem::select('name', DB::raw('SUM(quantity) as total_quantity'))
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->where('receipts.user_id', $userId)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->groupBy('name')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit);

        if ($startDate) {
            $query->where('receipts.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('receipts.created_at', '<=', $endDate . ' 23:59:59');
        }

        return $query->get();
    }

    /**
     * Format chart data for frontend consumption
     *
     * @param \Illuminate\Database\Eloquent\Collection $items
     * @return array
     */
    public function formatChartData($items): array
    {
        return $items->map(function ($item) {
            return [
                'name' => $item->name,
                'quantity' => (int) $item->total_quantity
            ];
        })->toArray();
    }

    /**
     * Get dashboard statistics
     *
     * @param int $userId
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getDashboardStats(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        $receiptQuery = DB::table('receipts')->where('user_id', $userId);
        $itemQuery = DB::table('receipt_items')
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->where('receipts.user_id', $userId);

        if ($startDate) {
            $receiptQuery->where('created_at', '>=', $startDate);
            $itemQuery->where('receipts.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $receiptQuery->where('created_at', '<=', $endDate . ' 23:59:59');
            $itemQuery->where('receipts.created_at', '<=', $endDate . ' 23:59:59');
        }

        return [
            'total_receipts' => $receiptQuery->count(),
            'total_items' => $itemQuery->sum('quantity'),
            'total_spent' => $receiptQuery->sum('total_amount'),
            'average_receipt_value' => $receiptQuery->avg('total_amount'),
        ];
    }

    /**
     * Get spending by category
     *
     * @param int $userId
     * @param string|null $startDate
     * @param string|null $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSpendingByCategory(int $userId, ?string $startDate = null, ?string $endDate = null)
    {
        $query = DB::table('receipt_items')
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->join('categories', 'receipt_items.category_id', '=', 'categories.id')
            ->select('categories.name as category_name', DB::raw('SUM(receipt_items.total_price) as total_spent'))
            ->where('receipts.user_id', $userId)
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_spent', 'desc');

        if ($startDate) {
            $query->where('receipts.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('receipts.created_at', '<=', $endDate . ' 23:59:59');
        }

        return $query->get();
    }
}