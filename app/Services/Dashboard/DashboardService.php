<?php

namespace App\Services\Dashboard;

use App\Models\ReceiptItem;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get the most bought items for chart data
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMostBoughtItems(int $userId, ?string $startDate = null, ?string $endDate = null, int $limit = 10, ?int $categoryId = null)
    {
        $query = ReceiptItem::select(
            'categories.name as category_name',
            'receipt_items.name as item_name',
            DB::raw('SUM(receipt_items.quantity) as total_quantity')
        )
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->join('categories', 'receipt_items.category_id', '=', 'categories.id')
            ->where('receipts.user_id', $userId)
            ->whereNotNull('receipt_items.name')
            ->where('receipt_items.name', '!=', '')
            ->groupBy('categories.id', 'categories.name', 'receipt_items.name')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit);

        if ($startDate) {
            $query->where('receipts.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('receipts.created_at', '<=', $endDate.' 23:59:59');
        }

        if ($categoryId) {
            // Filter by category and its subcategories
            $category = \App\Models\Category::find($categoryId);
            if ($category) {
                if ($category->isParent()) {
                    // If it's a parent category, include all its subcategories
                    $subcategoryIds = $category->subcategories->pluck('id')->toArray();
                    $subcategoryIds[] = $categoryId; // Include the parent category itself
                    $query->whereIn('receipt_items.category_id', $subcategoryIds);
                } else {
                    // If it's a subcategory, filter by that specific category
                    $query->where('receipt_items.category_id', $categoryId);
                }
            }
        }

        return $query->get();
    }

    /**
     * Format chart data for frontend consumption with Y-axis grouping
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $items
     */
    public function formatChartData($items): array
    {
        // Group items by category for Y-axis grouping
        $groupedData = $items->groupBy('category_name');

        $chartData = [];
        $categories = $groupedData->keys()->toArray();

        // Create data structure for grouped chart
        foreach ($groupedData as $categoryName => $categoryItems) {
            foreach ($categoryItems as $item) {
                $chartData[] = [
                    'item' => $item->item_name,
                    'category' => $categoryName,
                    'quantity' => (int) $item->total_quantity,
                ];
            }
        }

        return [
            'data' => $chartData,
            'categories' => $categories,
        ];
    }

    /**
     * Get all categories with their subcategories for filter dropdown
     */
    public function getCategoriesForFilter(): array
    {
        $categories = \App\Models\Category::with('subcategories')->get();

        $filterOptions = [];

        foreach ($categories as $category) {
            if ($category->isParent()) {
                // Add parent category
                $filterOptions[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => 'parent',
                    'subcategories' => $category->subcategories->count(),
                ];

                // Add subcategories
                foreach ($category->subcategories as $subcategory) {
                    $filterOptions[] = [
                        'id' => $subcategory->id,
                        'name' => '  └ '.$subcategory->name,
                        'type' => 'subcategory',
                        'parent_id' => $category->id,
                        'parent_name' => $category->name,
                    ];
                }
            }
        }

        return $filterOptions;
    }

    /**
     * Get dashboard statistics
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
            $receiptQuery->where('created_at', '<=', $endDate.' 23:59:59');
            $itemQuery->where('receipts.created_at', '<=', $endDate.' 23:59:59');
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
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSpendingByCategory(int $userId, ?string $startDate = null, ?string $endDate = null)
    {
        $query = DB::table('receipt_items')
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->join('categories', 'receipt_items.category_id', '=', 'categories.id')
            ->select('categories.name as category_name', DB::raw('SUM(receipt_items.total) as total_spent'))
            ->where('receipts.user_id', $userId)
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_spent', 'desc');

        if ($startDate) {
            $query->where('receipts.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('receipts.created_at', '<=', $endDate.' 23:59:59');
        }

        return $query->get();
    }
}
