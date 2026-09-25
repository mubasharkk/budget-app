<?php

namespace App\Http\Controllers;

use App\Domain\Contracts\Services\ContractService;
use App\Http\Requests\ContractRequest;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function __construct(private ContractService $contractService) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $contracts = $this->contractService->listForUser($userId);

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'summary' => $this->contractService->summary($userId, $contracts),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Contracts/Create', $this->contractService->formOptions($request->user()->id));
    }

    public function store(ContractRequest $request): RedirectResponse
    {
        $this->contractService->create($request->user(), $request->validated());

        return redirect()->route('contracts.index')
            ->with('success', 'Contract created successfully.');
    }

    public function show(Contract $contract): Response
    {
        $this->authorize('view', $contract);

        return Inertia::render('Contracts/Show', [
            'contract' => $contract->load(['provider', 'category']),
        ]);
    }

    public function edit(Request $request, Contract $contract): Response
    {
        $this->authorize('update', $contract);

        return Inertia::render('Contracts/Edit', [
            'contract' => $contract,
            ...$this->contractService->formOptions($request->user()->id),
        ]);
    }

    public function update(ContractRequest $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $this->contractService->update($contract, $request->validated());

        return redirect()->route('contracts.index')
            ->with('success', 'Contract updated successfully.');
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        $this->contractService->delete($contract);

        return redirect()->route('contracts.index')
            ->with('success', 'Contract deleted successfully.');
    }

    public function markPaid(Contract $contract): RedirectResponse
    {
        $this->authorize('markPaid', $contract);

        $this->contractService->markAsPaid($contract);

        return redirect()->back()
            ->with('success', 'Contract marked as paid.');
    }
}
