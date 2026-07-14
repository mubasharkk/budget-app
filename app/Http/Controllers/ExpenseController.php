<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseType;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        $expenses = Expense::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'summary' => [
                'total' => round((float) $expenses->sum('amount'), 2),
                'personal' => round((float) $expenses->where('expense_type', ExpenseType::Personal)->sum('amount'), 2),
                'business' => round((float) $expenses->where('expense_type', ExpenseType::Business)->sum('amount'), 2),
                'count' => $expenses->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Expenses/Create', $this->formOptions());
    }

    public function store(ExpenseRequest $request)
    {
        Auth::user()->expenses()->create($request->validated());

        return redirect()->route('expenses.index')
            ->with('success', 'Expense recorded successfully.');
    }

    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('Expenses/Edit', array_merge(
            ['expense' => $expense],
            $this->formOptions(),
        ));
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $expense->update($request->validated());

        return redirect()->route('expenses.index')
            ->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'expenseTypes' => ExpenseType::options(),
            'currencies' => ['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP'],
        ];
    }
}
