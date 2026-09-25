<?php

namespace App\Http\Controllers;

use App\Domain\Budgets\Services\BudgetService;
use App\Domain\Categories\Services\CategoryService;
use App\Domain\Shared\Support\SupportedCurrencies;
use App\Enums\BudgetPeriod;
use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetService $budgetService,
        private CategoryService $categoryService,
    ) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $period = BudgetPeriod::tryFrom((string) $request->query('period')) ?? BudgetPeriod::Monthly;

        return Inertia::render('Budgets/Index', [
            'period' => $period->value,
            'summary' => $this->budgetService->summary($userId, $period),
            'budgets' => $this->budgetService->listForUser($userId, $period),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Budgets/Create', $this->formOptions());
    }

    public function store(BudgetRequest $request): RedirectResponse
    {
        $this->budgetService->create($request->user(), $request->validated());

        return redirect()->route('budgets.index', ['period' => $request->input('period')])
            ->with('success', 'Budget created successfully.');
    }

    public function edit(Budget $budget): Response
    {
        $this->authorize('update', $budget);

        return Inertia::render('Budgets/Edit', [
            'budget' => $budget->load('category:id,name'),
            ...$this->formOptions(),
        ]);
    }

    public function update(BudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $this->budgetService->update($budget, $request->validated());

        return redirect()->route('budgets.index', ['period' => $request->input('period')])
            ->with('success', 'Budget updated successfully.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $period = $budget->period->value;
        $this->budgetService->delete($budget);

        return redirect()->route('budgets.index', ['period' => $period])
            ->with('success', 'Budget deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => $this->categoryService->parentOptions(),
            'periods' => BudgetPeriod::options(),
            'currencies' => SupportedCurrencies::all(),
        ];
    }
}
