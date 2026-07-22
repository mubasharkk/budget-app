<?php

namespace App\Services;

use App\Enums\BudgetPeriod;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class SpendingQueryExecutor
{
    public function __construct(
        private ExpenseService $expenseService,
        private ConsumptionService $consumptionService,
        private BudgetService $budgetService,
    ) {}

    /**
     * Execute a validated structured query and return raw results.
     *
     * @param  array{intent: string, category?: ?string, vendor?: ?string, item?: ?string, start_date: string, end_date: string, metric?: ?string}  $parsed
     * @return array<string, mixed>
     */
    public function execute(int $userId, array $parsed): array
    {
        $start = CarbonImmutable::parse($parsed['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($parsed['end_date'])->endOfDay();

        return match ($parsed['intent']) {
            'total_spend' => $this->totalSpend($userId, $start, $end),
            'category_spend' => $this->categorySpend($userId, $start, $end, $parsed['category'] ?? null),
            'vendor_spend' => $this->vendorSpend($userId, $start, $end, $parsed['vendor'] ?? null),
            'budget_status' => $this->budgetStatus($userId),
            'top_items' => $this->topItems($userId, $start, $end, $parsed['metric'] ?? 'spend', $parsed['category_id'] ?? null),
            'item_search' => $this->itemSearch($userId, $start, $end, $parsed['item'] ?? '', $parsed['metric'] ?? 'quantity', $parsed['category_id'] ?? null),
            'category_search' => $this->categorySearch($userId, $parsed['category'] ?? ''),
            default => throw new \InvalidArgumentException('Unsupported query intent.'),
        };
    }

    /**
     * @return array{intent: string, category?: ?string, vendor?: ?string, item?: ?string, start_date: string, end_date: string, metric?: ?string}
     */
    public function validateParsedQuery(int $userId, array $parsed): array
    {
        $intent = $parsed['intent'] ?? '';
        $allowed = ['total_spend', 'category_spend', 'vendor_spend', 'budget_status', 'top_items', 'item_search', 'category_search'];

        if (! in_array($intent, $allowed, true)) {
            throw new \InvalidArgumentException('Query intent is not allowed.');
        }

        $start = $parsed['start_date'] ?? null;
        $end = $parsed['end_date'] ?? null;

        if ($intent === 'budget_status') {
            return [
                'intent' => $intent,
                'category' => null,
                'vendor' => null,
                'item' => null,
                'start_date' => CarbonImmutable::today()->startOfMonth()->toDateString(),
                'end_date' => CarbonImmutable::today()->endOfMonth()->toDateString(),
                'metric' => null,
            ];
        }

        if ($intent === 'category_search') {
            if (empty($parsed['category'])) {
                throw new \InvalidArgumentException('A category to search for is required.');
            }

            return [
                'intent' => $intent,
                'category' => $parsed['category'],
                'vendor' => null,
                'item' => null,
                'start_date' => $start ?: CarbonImmutable::today()->startOfYear()->toDateString(),
                'end_date' => $end ?: CarbonImmutable::today()->endOfYear()->toDateString(),
                'metric' => null,
            ];
        }

        if (! $start || ! $end) {
            throw new \InvalidArgumentException('A date range is required for this question.');
        }

        if ($intent === 'category_spend') {
            $category = $this->resolveCategoryName($parsed['category'] ?? null);
            if (! $category) {
                throw new \InvalidArgumentException('Category not recognized.');
            }
            $parsed['category'] = $category;
        }

        if ($intent === 'vendor_spend' && empty($parsed['vendor'])) {
            throw new \InvalidArgumentException('Vendor name is required.');
        }

        if ($intent === 'item_search' && empty($parsed['item'])) {
            throw new \InvalidArgumentException('An item to search for is required.');
        }

        if ($intent === 'top_items') {
            $metric = $parsed['metric'] ?? 'spend';
            $parsed['metric'] = in_array($metric, ['spend', 'quantity'], true) ? $metric : 'spend';
        }

        if ($intent === 'item_search') {
            $metric = $parsed['metric'] ?? 'quantity';
            $parsed['metric'] = in_array($metric, ['spend', 'quantity'], true) ? $metric : 'quantity';
        }

        return [
            'intent' => $intent,
            'category' => $parsed['category'] ?? null,
            'vendor' => $parsed['vendor'] ?? null,
            'item' => $parsed['item'] ?? null,
            'start_date' => $start,
            'end_date' => $end,
            'metric' => $parsed['metric'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function totalSpend(int $userId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $overview = $this->expenseService->overview($userId, $start, $end, 'month');

        return [
            'intent' => 'total_spend',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'fixed' => $overview['fixed'],
            'variable' => $overview['variable'],
            'total' => $overview['total'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categorySpend(int $userId, CarbonImmutable $start, CarbonImmutable $end, ?string $category): array
    {
        $overview = $this->expenseService->overview($userId, $start, $end, 'month');
        $row = collect($overview['by_category'])->firstWhere('category', $category);

        return [
            'intent' => 'category_spend',
            'category' => $category,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'fixed' => (float) ($row['fixed'] ?? 0),
            'variable' => (float) ($row['variable'] ?? 0),
            'total' => (float) ($row['total'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vendorSpend(int $userId, CarbonImmutable $start, CarbonImmutable $end, string $vendor): array
    {
        $vendors = $this->consumptionService->vendorLeaderboard($userId, $start->toDateString(), $end->toDateString(), 50);
        $match = $vendors->first(fn ($row) => Str::lower($row->vendor) === Str::lower($vendor));

        return [
            'intent' => 'vendor_spend',
            'vendor' => $vendor,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'receipt_count' => (int) ($match->receipt_count ?? 0),
            'total' => (float) ($match->total_spent ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetStatus(int $userId): array
    {
        $summary = $this->budgetService->summary($userId, BudgetPeriod::Monthly);

        return [
            'intent' => 'budget_status',
            'budgeted' => $summary['budgeted'],
            'actual' => $summary['actual'],
            'remaining' => $summary['remaining'],
            'over_count' => $summary['over_count'],
            'warning_count' => $summary['warning_count'],
            'items' => $summary['items'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function topItems(int $userId, CarbonImmutable $start, CarbonImmutable $end, string $metric, ?int $categoryId = null): array
    {
        $items = $this->consumptionService->topItems(
            $userId,
            $metric,
            $start->toDateString(),
            $end->toDateString(),
            $categoryId,
            5,
        );

        return [
            'intent' => 'top_items',
            'metric' => $metric,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'items' => $items->map(fn ($row): array => [
                'name' => $row->item_name,
                'category' => $row->category_name,
                'quantity' => (float) $row->total_quantity,
                'spend' => (float) $row->total_spend,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemSearch(int $userId, CarbonImmutable $start, CarbonImmutable $end, string $item, string $metric, ?int $categoryId = null): array
    {
        $items = $this->consumptionService->searchItems(
            $userId,
            $item,
            $start->toDateString(),
            $end->toDateString(),
            $categoryId,
            $metric,
            10,
        );

        return [
            'intent' => 'item_search',
            'item' => $item,
            'metric' => $metric,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'items' => $items->map(fn ($row): array => [
                'name' => $row->item_name,
                'category' => $row->category_name,
                'quantity' => (float) $row->total_quantity,
                'spend' => (float) $row->total_spend,
                'purchases' => (int) $row->purchase_count,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categorySearch(int $userId, string $term): array
    {
        $categories = $this->consumptionService->searchCategories($userId, $term);

        return [
            'intent' => 'category_search',
            'term' => $term,
            'categories' => $categories->map(fn ($row): array => [
                'category' => $row->category,
                'is_parent' => $row->is_parent,
                'spend' => (float) $row->total_spend,
                'item_count' => (int) $row->item_count,
            ])->all(),
        ];
    }

    private function resolveCategoryName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        $category = Category::query()
            ->whereNull('parent_id')
            ->where(function ($query) use ($name): void {
                $query->where('name', $name)
                    ->orWhere('slug', Str::slug($name));
            })
            ->first();

        return $category?->name;
    }
}
