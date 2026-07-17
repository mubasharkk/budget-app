<?php

namespace App\Http\Controllers;

use App\Enums\IncomeType;
use App\Http\Requests\IncomeRequest;
use App\Http\Requests\IncomeUpdateRequest;
use App\Models\Income;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        return Inertia::render('Incomes/Index', array_merge([
            'incomes' => Income::query()
                ->where('user_id', $user->id)
                ->orderByDesc('received_on')
                ->orderByDesc('id')
                ->get(),
            'summary' => [
                'total' => round((float) Income::query()
                    ->where('user_id', $user->id)
                    ->sum('amount'), 2),
                'count' => Income::query()
                    ->where('user_id', $user->id)
                    ->count(),
            ],
            'monthlyIncome' => [
                'amount' => $user->monthly_income !== null ? (float) $user->monthly_income : null,
                'income_type' => $user->income_type?->value,
                'income_currency' => $user->income_currency ?? 'EUR',
            ],
        ], $this->formOptions()));
    }

    public function updateMonthly(IncomeUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! isset($validated['monthly_income']) || $validated['monthly_income'] === null) {
            $user->monthly_income = null;
            $user->income_type = null;
        } else {
            $user->fill([
                'monthly_income' => $validated['monthly_income'],
                'income_type' => $validated['income_type'] ?? IncomeType::Net,
                'income_currency' => $validated['income_currency'] ?? 'EUR',
            ]);
        }

        $user->save();

        return redirect()->route('incomes.index')
            ->with('success', 'Monthly income updated successfully.');
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
