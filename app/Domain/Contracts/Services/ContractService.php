<?php

namespace App\Domain\Contracts\Services;

use App\Domain\Categories\Services\CategoryService;
use App\Domain\Shared\Support\SupportedCurrencies;
use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ExpenseType;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Collection;

class ContractService
{
    public function __construct(
        private ContractBillingService $billingService,
        private ProviderService $providerService,
        private CategoryService $categoryService,
    ) {}

    /**
     * All of the user's contracts with provider/category loaded.
     *
     * @return Collection<int, Contract>
     */
    public function listForUser(int $userId): Collection
    {
        return Contract::query()
            ->with(['provider', 'category'])
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Headline figures for the contracts overview: due this month plus normalised monthly/yearly cost.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array{due_this_month: float, due_this_month_count: int, paid_this_month_count: int, month_label: string, monthly_total: float, yearly_total: float, active_count: int}
     */
    public function summary(int $userId, Collection $contracts): array
    {
        $active = $contracts->where('status', ContractStatus::Active);
        $monthlyTotal = $active->sum(fn (Contract $contract): float => $contract->projectedMonthlyAmount());
        $dueThisMonth = $this->billingService->dueThisMonthSummary($userId);

        return [
            'due_this_month' => $dueThisMonth['total'],
            'due_this_month_count' => $dueThisMonth['count'],
            'paid_this_month_count' => $dueThisMonth['paid_count'],
            'month_label' => $dueThisMonth['month'],
            'monthly_total' => round($monthlyTotal, 2),
            'yearly_total' => round($monthlyTotal * 12, 2),
            'active_count' => $active->count(),
        ];
    }

    /**
     * Select options shared by the create/edit forms.
     *
     * @return array<string, mixed>
     */
    public function formOptions(int $userId): array
    {
        return [
            'providers' => $this->providerService->options($userId),
            'categories' => $this->categoryService->parentOptions(),
            'billingCycles' => BillingCycle::options(),
            'statuses' => ContractStatus::options(),
            'expenseTypes' => ExpenseType::options(),
            'currencies' => SupportedCurrencies::all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Contract
    {
        return $user->contracts()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        $contract->update($data);

        return $contract;
    }

    public function delete(Contract $contract): void
    {
        $contract->delete();
    }

    public function markAsPaid(Contract $contract): Contract
    {
        return $this->billingService->markAsPaid($contract);
    }
}
