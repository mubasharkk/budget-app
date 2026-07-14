<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseType;
use App\Http\Requests\ExpenseRequest;
use App\Jobs\ParseExpenseDocument;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        $expenses = Expense::query()
            ->where('user_id', Auth::id())
            ->with('media')
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

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = Auth::user()->expenses()->create($this->normalized($request));

        $this->syncDocument($request, $expense);

        return redirect()->route('expenses.index')
            ->with('success', 'Expense recorded successfully.');
    }

    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('Expenses/Edit', array_merge(
            ['expense' => $expense->load('media')],
            $this->formOptions(),
        ));
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $expense->update($this->normalized($request));

        $this->syncDocument($request, $expense);

        return redirect()->route('expenses.index')
            ->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted successfully.');
    }

    /**
     * Stream the attached document for owners only.
     */
    public function document(Expense $expense): BinaryFileResponse
    {
        $this->authorize('view', $expense);

        $media = $expense->getFirstMedia(Expense::DOCUMENT_COLLECTION);

        if ($media === null) {
            abort(404);
        }

        return response()->file($media->getPath());
    }

    /**
     * Apply the requested document change (add/replace or remove).
     */
    private function syncDocument(ExpenseRequest $request, Expense $expense): void
    {
        if ($request->boolean('remove_document')) {
            $expense->clearMediaCollection(Expense::DOCUMENT_COLLECTION);
        }

        if ($request->hasFile('document')) {
            $expense->addMediaFromRequest('document')
                ->toMediaCollection(Expense::DOCUMENT_COLLECTION);

            ParseExpenseDocument::dispatch($expense);
        }
    }

    /**
     * Validated data with a blank amount coalesced to 0 — the signal that the
     * amount should be read from the attached document.
     *
     * @return array<string, mixed>
     */
    private function normalized(ExpenseRequest $request): array
    {
        $data = $request->validated();
        $data['amount'] = $data['amount'] ?? 0;

        return $data;
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
