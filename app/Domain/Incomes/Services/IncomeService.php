<?php

namespace App\Domain\Incomes\Services;

use App\Domain\Shared\Support\PeriodRange;
use App\Enums\IncomeType;
use App\Models\Income;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IncomeService
{
    /**
     * @return Collection<int, Income>
     */
    public function listForUser(int $userId): Collection
    {
        return Income::query()
            ->where('user_id', $userId)
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{total: float, count: int}
     */
    public function summary(int $userId): array
    {
        $query = Income::query()->where('user_id', $userId);

        return [
            'total' => round((float) (clone $query)->sum('amount'), 2),
            'count' => $query->count(),
        ];
    }

    /**
     * The user's recurring monthly income settings.
     *
     * @return array{amount: ?float, income_type: ?string, default_currency: string}
     */
    public function monthlyIncome(User $user): array
    {
        return [
            'amount' => $user->monthly_income !== null ? (float) $user->monthly_income : null,
            'income_type' => $user->income_type?->value,
            'default_currency' => $user->default_currency,
        ];
    }

    /**
     * Set or clear the user's recurring monthly income; a null amount clears it.
     * A currency, when given, updates the user's single default currency.
     *
     * @param  array{monthly_income?: ?numeric, income_type?: ?string, default_currency?: ?string}  $data
     */
    public function updateMonthlyIncome(User $user, array $data): User
    {
        if (($data['monthly_income'] ?? null) === null) {
            $user->monthly_income = null;
            $user->income_type = null;
        } else {
            $user->fill([
                'monthly_income' => $data['monthly_income'],
                'income_type' => $data['income_type'] ?? IncomeType::Net,
            ]);
        }

        if (filled($data['default_currency'] ?? null)) {
            $user->default_currency = $data['default_currency'];
        }

        $user->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Income
    {
        return $user->incomes()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Income $income, array $data): Income
    {
        $income->update($data);

        return $income;
    }

    public function delete(Income $income): void
    {
        $income->delete();
    }

    /**
     * Income context for budget and spending comparisons.
     *
     * @return array<string, mixed>|null
     */
    public function context(
        User $user,
        float $actualSpend,
        float $budgetedTotal,
        string $period = 'month',
        ?CarbonInterface $anchor = null,
    ): ?array {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());
        [$start, $end] = PeriodRange::current($period, $anchor);

        $recurringPeriodIncome = $this->recurringForPeriod($user, $period);
        $oneTimePeriodIncome = $this->oneTimeForPeriod($user, $start, $end);
        $periodIncome = round($recurringPeriodIncome + $oneTimePeriodIncome, 2);

        if ($periodIncome <= 0) {
            return null;
        }

        $monthlyIncome = (float) ($user->monthly_income ?? 0);
        $incomeType = $user->income_type ?? IncomeType::Net;
        $currency = $user->default_currency;

        return [
            'monthly_income' => $monthlyIncome > 0 ? $monthlyIncome : null,
            'recurring_period_income' => $recurringPeriodIncome,
            'one_time_period_income' => $oneTimePeriodIncome,
            'period_income' => $periodIncome,
            'income_type' => $incomeType->value,
            'income_type_label' => $incomeType->shortLabel(),
            'currency' => $currency,
            'spend_percent' => $periodIncome > 0
                ? round(($actualSpend / $periodIncome) * 100, 1)
                : null,
            'budgeted_percent' => $periodIncome > 0
                ? round(($budgetedTotal / $periodIncome) * 100, 1)
                : null,
            'disposable' => round($periodIncome - $actualSpend, 2),
            'remaining_after_budgets' => round($periodIncome - $budgetedTotal, 2),
            'is_over_income' => $actualSpend > $periodIncome,
            'budgets_exceed_income' => $budgetedTotal > $periodIncome,
            'has_recurring_income' => $monthlyIncome > 0,
            'has_one_time_income' => $oneTimePeriodIncome > 0,
        ];
    }

    /**
     * Raw period income (recurring + one-time) regardless of whether it is zero.
     */
    public function periodIncome(User $user, string $period, ?CarbonInterface $anchor = null): float
    {
        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());
        [$start, $end] = PeriodRange::current($period, $anchor);

        return round(
            $this->recurringForPeriod($user, $period) + $this->oneTimeForPeriod($user, $start, $end),
            2,
        );
    }

    public function recurringForPeriod(User $user, string $period): float
    {
        if ($user->monthly_income === null || (float) $user->monthly_income <= 0) {
            return 0.0;
        }

        $monthlyIncome = (float) $user->monthly_income;

        return $period === 'week'
            ? round($monthlyIncome / 4.33, 2)
            : $monthlyIncome;
    }

    /**
     * One-time income received in the period, in the user's currency only.
     */
    public function oneTimeForPeriod(User $user, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return round((float) $this->oneTimeIn($user, $start, $end)
            ->where('currency', $user->default_currency)
            ->sum('amount'), 2);
    }

    /**
     * Adds the one-time income that oneTimeForPeriod() leaves out, because it is
     * in another currency, to a list of other-currency amounts (as returned by
     * ExpenseService::otherCurrencies()).
     *
     * @param  array<int, array{currency: string, spent: float, contracts: float}>  $otherCurrencies
     * @return array<int, array{currency: string, spent: float, contracts: float, income: float}>
     */
    public function withOtherCurrencyIncome(array $otherCurrencies, User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = collect($otherCurrencies)->keyBy('currency')->map(fn (array $row): array => $row + ['income' => 0.0]);

        $this->oneTimeIn($user, $start, $end)
            ->where('currency', '!=', $user->default_currency)
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount) as income')
            ->toBase()
            ->get()
            ->each(function (object $row) use ($rows): void {
                $rows->put($row->currency, [
                    'currency' => $row->currency,
                    'spent' => $rows->get($row->currency)['spent'] ?? 0.0,
                    'contracts' => $rows->get($row->currency)['contracts'] ?? 0.0,
                    'income' => round((float) $row->income, 2),
                ]);
            });

        return $rows->sortKeys()->values()->all();
    }

    /**
     * @return Builder<Income>
     */
    private function oneTimeIn(User $user, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Income::query()
            ->where('user_id', $user->id)
            ->whereBetween('received_on', [$start->toDateString(), $end->toDateString()]);
    }
}
