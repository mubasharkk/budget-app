<?php

namespace App\Http\Controllers;

use App\Domain\Incomes\Services\IncomeService;
use App\Domain\Shared\Support\SupportedCurrencies;
use App\Enums\IncomeType;
use App\Http\Requests\IncomeRequest;
use App\Http\Requests\IncomeUpdateRequest;
use App\Models\Income;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
    public function __construct(private IncomeService $incomeService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Incomes/Index', [
            'incomes' => $this->incomeService->listForUser($user->id),
            'summary' => $this->incomeService->summary($user->id),
            'monthlyIncome' => $this->incomeService->monthlyIncome($user),
            ...$this->formOptions(),
        ]);
    }

    public function updateMonthly(IncomeUpdateRequest $request): RedirectResponse
    {
        $this->incomeService->updateMonthlyIncome($request->user(), $request->validated());

        return redirect()->route('incomes.index')
            ->with('success', 'Monthly income updated successfully.');
    }

    public function create(): Response
    {
        return Inertia::render('Incomes/Create', $this->formOptions());
    }

    public function store(IncomeRequest $request): RedirectResponse
    {
        $this->incomeService->create($request->user(), $request->validated());

        return redirect()->route('incomes.index')
            ->with('success', 'Income recorded successfully.');
    }

    public function edit(Income $income): Response
    {
        $this->authorize('update', $income);

        return Inertia::render('Incomes/Edit', [
            'income' => $income,
            ...$this->formOptions(),
        ]);
    }

    public function update(IncomeRequest $request, Income $income): RedirectResponse
    {
        $this->authorize('update', $income);

        $this->incomeService->update($income, $request->validated());

        return redirect()->route('incomes.index')
            ->with('success', 'Income updated successfully.');
    }

    public function destroy(Income $income): RedirectResponse
    {
        $this->authorize('delete', $income);

        $this->incomeService->delete($income);

        return redirect()->route('incomes.index')
            ->with('success', 'Income deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'incomeTypes' => IncomeType::options(),
            'currencies' => SupportedCurrencies::all(),
        ];
    }
}
