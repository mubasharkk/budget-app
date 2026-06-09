<?php

namespace App\Http\Controllers;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Http\Requests\ContractRequest;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Provider;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(): Response
    {
        $contracts = Contract::query()
            ->with(['provider', 'category'])
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        $monthlyTotal = $contracts
            ->where('status', ContractStatus::Active)
            ->sum(fn (Contract $contract): float => $contract->projectedMonthlyAmount());

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'summary' => [
                'monthly_total' => round($monthlyTotal, 2),
                'yearly_total' => round($monthlyTotal * 12, 2),
                'active_count' => $contracts->where('status', ContractStatus::Active)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Contracts/Create', $this->formOptions());
    }

    public function store(ContractRequest $request)
    {
        Auth::user()->contracts()->create($request->validated());

        return redirect()->route('contracts.index')
            ->with('success', 'Contract created successfully.');
    }

    public function show(Contract $contract): Response
    {
        $this->authorize('view', $contract);

        $contract->load(['provider', 'category']);

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
        ]);
    }

    public function edit(Contract $contract): Response
    {
        $this->authorize('update', $contract);

        return Inertia::render('Contracts/Edit', array_merge(
            ['contract' => $contract],
            $this->formOptions(),
        ));
    }

    public function update(ContractRequest $request, Contract $contract)
    {
        $this->authorize('update', $contract);

        $contract->update($request->validated());

        return redirect()->route('contracts.index')
            ->with('success', 'Contract updated successfully.');
    }

    public function destroy(Contract $contract)
    {
        $this->authorize('delete', $contract);

        $contract->delete();

        return redirect()->route('contracts.index')
            ->with('success', 'Contract deleted successfully.');
    }

    /**
     * Shared select options for the create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'providers' => Provider::query()
                ->where('user_id', Auth::id())
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'name']),
            'billingCycles' => BillingCycle::options(),
            'statuses' => ContractStatus::options(),
            'currencies' => ['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP'],
        ];
    }
}
