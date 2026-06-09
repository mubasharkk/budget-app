<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ExpenseService
{
    /**
     * Combined fixed (contracts) + variable (receipts) overview for a date range.
     *
     * @return array{fixed: float, variable: float, total: float, by_category: array<int, array{category: string, fixed: float, variable: float, total: float}>}
     */
    public function overview(int $userId, CarbonInterface $start, CarbonInterface $end, string $period): array
    {
        $fixedByCategory = $this->fixedByCategory($userId, $period);
        $variableByCategory = $this->variableByCategory($userId, $start, $end);

        $fixedTotal = round(array_sum($fixedByCategory), 2);
        $variableTotal = round($this->variableTotal($userId, $start, $end), 2);

        return [
            'fixed' => $fixedTotal,
            'variable' => $variableTotal,
            'total' => round($fixedTotal + $variableTotal, 2),
            'by_category' => $this->mergeByCategory($fixedByCategory, $variableByCategory),
        ];
    }

    /**
     * Total variable spend (receipt totals) within the range, by receipt date.
     */
    public function variableTotal(int $userId, CarbonInterface $start, CarbonInterface $end): float
    {
        return (float) Receipt::query()
            ->where('user_id', $userId)
            ->whereBetween('receipt_date', [$start, $end])
            ->sum('total_amount');
    }

    /**
     * Variable spend grouped by top-level category, from receipt line items.
     *
     * @return array<string, float>
     */
    public function variableByCategory(int $userId, CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->leftJoin('categories', 'receipt_items.category_id', '=', 'categories.id')
            ->where('receipts.user_id', $userId)
            ->whereBetween('receipts.receipt_date', [$start, $end])
            ->groupBy('categories.name')
            ->selectRaw('categories.name as name, SUM(receipt_items.total) as total')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $name = $row->name ?? 'Uncategorized';
            $result[$name] = round(($result[$name] ?? 0) + (float) $row->total, 2);
        }

        return $result;
    }

    /**
     * Active contracts' recurring cost grouped by category, normalized to the period.
     *
     * @param  string  $period  'week' or 'month'
     * @return array<string, float>
     */
    public function fixedByCategory(int $userId, string $period): array
    {
        $contracts = Contract::query()
            ->with('category:id,name')
            ->where('user_id', $userId)
            ->where('status', ContractStatus::Active)
            ->get();

        $result = [];
        foreach ($contracts as $contract) {
            $amount = $period === 'week'
                ? $contract->billing_cycle->toWeeklyAmount((float) $contract->amount)
                : $contract->billing_cycle->toMonthlyAmount((float) $contract->amount);

            $name = $contract->category?->name ?? 'Uncategorized';
            $result[$name] = round(($result[$name] ?? 0) + $amount, 2);
        }

        return $result;
    }

    /**
     * Spending trend over the most recent N periods (oldest first).
     *
     * @return array<int, array{label: string, fixed: float, variable: float, total: float}>
     */
    public function trend(int $userId, CarbonInterface $anchor, string $period, int $points): array
    {
        $anchor = CarbonImmutable::instance($anchor);
        $fixed = round(array_sum($this->fixedByCategory($userId, $period)), 2);

        $buckets = [];
        for ($i = $points - 1; $i >= 0; $i--) {
            if ($period === 'week') {
                $start = $anchor->startOfWeek(CarbonInterface::MONDAY)->subWeeks($i);
                $end = $start->endOfWeek(CarbonInterface::SUNDAY);
                $label = $start->format('d M');
            } else {
                $start = $anchor->startOfMonth()->subMonths($i);
                $end = $start->endOfMonth();
                $label = $start->format('M Y');
            }

            $variable = round($this->variableTotal($userId, $start, $end), 2);

            $buckets[] = [
                'label' => $label,
                'fixed' => $fixed,
                'variable' => $variable,
                'total' => round($fixed + $variable, 2),
            ];
        }

        return $buckets;
    }

    /**
     * Merge fixed + variable category maps into a sorted list.
     *
     * @param  array<string, float>  $fixed
     * @param  array<string, float>  $variable
     * @return array<int, array{category: string, fixed: float, variable: float, total: float}>
     */
    private function mergeByCategory(array $fixed, array $variable): array
    {
        $names = array_unique([...array_keys($fixed), ...array_keys($variable)]);

        $list = [];
        foreach ($names as $name) {
            $f = round($fixed[$name] ?? 0, 2);
            $v = round($variable[$name] ?? 0, 2);
            $list[] = [
                'category' => $name,
                'fixed' => $f,
                'variable' => $v,
                'total' => round($f + $v, 2),
            ];
        }

        usort($list, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $list;
    }
}
