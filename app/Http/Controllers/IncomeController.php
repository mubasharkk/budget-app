<?php

namespace App\Http\Controllers;

use App\Enums\IncomeType;
use App\Http\Requests\IncomeRequest;
use App\Models\Income;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Incomes/Index', [
            'incomes' => Income::query()
                ->where('user_id', Auth::id())
                ->orderByDesc('received_on')
                ->orderByDesc('id')
                ->get(),
            'summary' => [
                'total' => round((float) Income::query()
                    ->where('user_id', Auth::id())
                    ->sum('amount'), 2),
                'count' => Income::query()
                    ->where('user_id', Auth::id())
                    ->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Incomes/Create', $this->formOptions());
    }

    public function store(IncomeRequest $request)
    {
        Auth::user()->incomes()->create($request->validated());

        return redirect()->route('incomes.index')
            ->with('success', 'Income recorded successfully.');
    }

    public function edit(Income $income): Response
    {
        $this->authorize('update', $income);

        return Inertia::render('Incomes/Edit', array_merge(
            ['income' => $income],
            $this->formOptions(),
        ));
    }

    public function update(IncomeRequest $request, Income $income)
    {
        $this->authorize('update', $income);

        $income->update($request->validated());

        return redirect()->route('incomes.index')
            ->with('success', 'Income updated successfully.');
    }

    public function destroy(Income $income)
    {
        $this->authorize('delete', $income);

        $income->delete();

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
            'currencies' => ['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP'],
        ];
    }
}
