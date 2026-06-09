<?php

namespace App\Http\Controllers;

use App\Enums\BudgetPeriod;
use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(private BudgetService $budgetService) {}

    public function index(Request $request): Response
    {
        $period = $request->get('period') === 'weekly'
            ? BudgetPeriod::Weekly
            : BudgetPeriod::Monthly;

        return Inertia::render('Budgets/Index', [
            'period' => $period->value,
            'summary' => $this->budgetService->summary(Auth::id(), $period),
            'budgets' => Budget::query()
                ->with('category:id,name')
                ->where('user_id', Auth::id())
                ->where('period', $period)
                ->orderBy('category_id')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Budgets/Create', $this->formOptions());
    }

    public function store(BudgetRequest $request)
    {
        Auth::user()->budgets()->create($request->validated());

        return redirect()->route('budgets.index', ['period' => $request->input('period')])
            ->with('success', 'Budget created successfully.');
    }

    public function edit(Budget $budget): Response
    {
        $this->authorize('update', $budget);

        return Inertia::render('Budgets/Edit', array_merge(
            ['budget' => $budget->load('category:id,name')],
            $this->formOptions(),
        ));
    }

    public function update(BudgetRequest $request, Budget $budget)
    {
        $this->authorize('update', $budget);

        $budget->update($request->validated());

        return redirect()->route('budgets.index', ['period' => $request->input('period')])
            ->with('success', 'Budget updated successfully.');
    }

    public function destroy(Budget $budget)
    {
        $this->authorize('delete', $budget);

        $period = $budget->period->value;
        $budget->delete();

        return redirect()->route('budgets.index', ['period' => $period])
            ->with('success', 'Budget deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'name']),
            'periods' => BudgetPeriod::options(),
            'currencies' => ['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP'],
        ];
    }
}
