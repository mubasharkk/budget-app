<?php

namespace App\Services;

use App\Models\Receipt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AnomalyDetectionService
{
    /**
     * Detect spending anomalies for a user within a date range.
     *
     * @return array<int, array{type: string, severity: string, title: string, description: string, metadata: array<string, mixed>}>
     */
    public function detect(
        int $userId,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
    ): array {
        $end = CarbonImmutable::instance($end ?? CarbonImmutable::today());
        $start = CarbonImmutable::instance($start ?? $end->copy()->startOfMonth());

        return [
            ...$this->detectDuplicateCharges($userId, $start, $end),
            ...$this->detectLargeReceipts($userId, $start, $end),
            ...$this->detectCategorySpikes($userId, $start, $end),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectDuplicateCharges(int $userId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $duplicates = Receipt::query()
            ->where('user_id', $userId)
            ->whereBetween('receipt_date', [$start, $end.' 23:59:59'])
            ->whereNotNull('vendor')
            ->where('total_amount', '>', 0)
            ->select([
                'vendor',
                'total_amount',
                DB::raw('DATE(receipt_date) as charge_date'),
                DB::raw('COUNT(*) as occurrence_count'),
            ])
            ->groupBy('vendor', 'total_amount', DB::raw('DATE(receipt_date)'))
            ->having('occurrence_count', '>', 1)
            ->get();

        return $duplicates->map(fn ($row): array => [
            'type' => 'duplicate_charge',
            'severity' => 'high',
            'title' => 'Possible duplicate charge',
            'description' => sprintf(
                '%s charged €%s on %s (%d times)',
                $row->vendor,
                number_format((float) $row->total_amount, 2),
                $row->charge_date,
                $row->occurrence_count,
            ),
            'metadata' => [
                'vendor' => $row->vendor,
                'amount' => (float) $row->total_amount,
                'date' => $row->charge_date,
                'count' => (int) $row->occurrence_count,
            ],
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectLargeReceipts(int $userId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $amounts = Receipt::query()
            ->where('user_id', $userId)
            ->where('total_amount', '>', 0)
            ->whereBetween('receipt_date', [$start->copy()->subMonths(3), $end])
            ->orderBy('total_amount')
            ->pluck('total_amount')
            ->map(fn ($value): float => (float) $value)
            ->values();

        if ($amounts->isEmpty()) {
            return [];
        }

        $count = $amounts->count();
        $median = $count % 2 === 0
            ? ($amounts[$count / 2 - 1] + $amounts[$count / 2]) / 2
            : $amounts[intdiv($count, 2)];

        if ($median <= 0) {
            return [];
        }

        $threshold = $median * 2;

        return Receipt::query()
            ->where('user_id', $userId)
            ->whereBetween('receipt_date', [$start, $end.' 23:59:59'])
            ->where('total_amount', '>', $threshold)
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(fn (Receipt $receipt): array => [
                'type' => 'large_receipt',
                'severity' => 'medium',
                'title' => 'Unusually large receipt',
                'description' => sprintf(
                    '%s — €%s on %s (median receipt: €%s)',
                    $receipt->vendor ?? 'Unknown vendor',
                    number_format((float) $receipt->total_amount, 2),
                    $receipt->receipt_date?->toDateString() ?? 'unknown date',
                    number_format((float) $median, 2),
                ),
                'metadata' => [
                    'receipt_id' => $receipt->id,
                    'vendor' => $receipt->vendor,
                    'amount' => (float) $receipt->total_amount,
                    'median' => (float) $median,
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectCategorySpikes(int $userId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $expenseService = new ExpenseService;
        $current = collect($expenseService->overview($userId, $start, $end, 'month')['by_category'])
            ->keyBy('category');

        $previousStart = $start->copy()->subMonth()->startOfMonth();
        $previousEnd = $start->copy()->subMonth()->endOfMonth();
        $previous = collect($expenseService->overview($userId, $previousStart, $previousEnd, 'month')['by_category'])
            ->keyBy('category');

        $anomalies = [];

        foreach ($current as $category => $row) {
            $prevTotal = (float) ($previous->get($category)['total'] ?? 0);
            $currTotal = (float) $row['total'];

            if ($prevTotal <= 0 || $currTotal <= 50) {
                continue;
            }

            $changePercent = (($currTotal - $prevTotal) / $prevTotal) * 100;

            if ($changePercent < 50) {
                continue;
            }

            $anomalies[] = [
                'type' => 'category_spike',
                'severity' => $changePercent >= 100 ? 'high' : 'medium',
                'title' => 'Category spend spike',
                'description' => sprintf(
                    '%s spend is up %.0f%% (€%s vs €%s last month)',
                    $category,
                    $changePercent,
                    number_format($currTotal, 2),
                    number_format($prevTotal, 2),
                ),
                'metadata' => [
                    'category' => $category,
                    'current' => $currTotal,
                    'previous' => $prevTotal,
                    'change_percent' => round($changePercent, 1),
                ],
            ];
        }

        return $anomalies;
    }
}
