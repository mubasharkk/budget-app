<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Shared\Support\PeriodRange;
use App\Models\Contract;
use App\Models\Income;
use App\Models\Receipt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TransactionLedgerService
{
    /**
     * Every dated income and expense entry in the range, newest first, with totals.
     *
     * Expenses are expense receipts plus each contract's last recorded payment (contracts
     * keep no payment history). Income is one-time income entries, which already include
     * income receipts. A missing bound defaults to the current month.
     *
     * @return array{start: string, end: string, totals: array{income: float, expenses: float, net: float}, transactions: array<int, array{key: string, type: string, source: string, id: int, date: string, description: string, amount: float}>}
     */
    public function forRange(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$monthStart, $monthEnd] = PeriodRange::current('month');
        $start = $startDate ? CarbonImmutable::parse($startDate)->startOfDay() : $monthStart->startOfDay();
        $end = $endDate ? CarbonImmutable::parse($endDate)->endOfDay() : $monthEnd->endOfDay();

        $transactions = $this->receiptExpenses($userId, $start, $end)
            ->concat($this->contractPayments($userId, $start, $end))
            ->concat($this->incomes($userId, $start, $end))
            ->sortBy([['date', 'desc'], ['key', 'desc']])
            ->values();

        $income = round((float) $transactions->where('type', 'income')->sum('amount'), 2);
        $expenses = round((float) $transactions->where('type', 'expense')->sum('amount'), 2);

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'totals' => [
                'income' => $income,
                'expenses' => $expenses,
                'net' => round($income - $expenses, 2),
            ],
            'transactions' => $transactions->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function receiptExpenses(int $userId, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Receipt::query()
            ->expenses()
            ->where('user_id', $userId)
            ->whereBetween('receipt_date', [$start, $end])
            ->where('total_amount', '>', 0)
            ->get(['id', 'vendor', 'original_filename', 'total_amount', 'receipt_date'])
            ->map(fn (Receipt $receipt): array => $this->entry(
                'expense',
                'receipt',
                $receipt->id,
                $receipt->receipt_date->toDateString(),
                $receipt->vendor ?: $receipt->original_filename,
                (float) $receipt->total_amount,
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function contractPayments(int $userId, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Contract::query()
            ->where('user_id', $userId)
            ->whereDate('last_paid_at', '>=', $start)
            ->whereDate('last_paid_at', '<=', $end)
            ->get(['id', 'name', 'amount', 'last_paid_at'])
            ->map(fn (Contract $contract): array => $this->entry(
                'expense',
                'contract',
                $contract->id,
                CarbonImmutable::parse($contract->last_paid_at)->toDateString(),
                $contract->name,
                (float) $contract->amount,
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function incomes(int $userId, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Income::query()
            ->where('user_id', $userId)
            ->whereDate('received_on', '>=', $start)
            ->whereDate('received_on', '<=', $end)
            ->get(['id', 'source', 'amount', 'received_on'])
            ->map(fn (Income $income): array => $this->entry(
                'income',
                'income',
                $income->id,
                $income->received_on->toDateString(),
                $income->source ?: 'One-time income',
                (float) $income->amount,
            ));
    }

    /**
     * @return array{key: string, type: string, source: string, id: int, date: string, description: string, amount: float}
     */
    private function entry(string $type, string $source, int $id, string $date, string $description, float $amount): array
    {
        return [
            'key' => $source.'-'.$id,
            'type' => $type,
            'source' => $source,
            'id' => $id,
            'date' => $date,
            'description' => $description,
            'amount' => round($amount, 2),
        ];
    }
}
