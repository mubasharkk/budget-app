<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavingRequest;
use App\Models\Saving;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SavingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Savings/Index', [
            'savings' => Saving::query()
                ->where('user_id', Auth::id())
                ->orderByDesc('saved_on')
                ->orderByDesc('id')
                ->get(),
            'summary' => [
                'total' => round((float) Saving::query()
                    ->where('user_id', Auth::id())
                    ->sum('amount'), 2),
                'count' => Saving::query()
                    ->where('user_id', Auth::id())
                    ->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Savings/Create', $this->formOptions());
    }

    public function store(SavingRequest $request)
    {
        Auth::user()->savings()->create($request->validated());

        return redirect()->route('savings.index')
            ->with('success', 'Savings recorded successfully.');
    }

    public function edit(Saving $saving): Response
    {
        $this->authorize('update', $saving);

        return Inertia::render('Savings/Edit', array_merge(
            ['saving' => $saving],
            $this->formOptions(),
        ));
    }

    public function update(SavingRequest $request, Saving $saving)
    {
        $this->authorize('update', $saving);

        $saving->update($request->validated());

        return redirect()->route('savings.index')
            ->with('success', 'Savings updated successfully.');
    }

    public function destroy(Saving $saving)
    {
        $this->authorize('delete', $saving);

        $saving->delete();

        return redirect()->route('savings.index')
            ->with('success', 'Savings deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'currencies' => ['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP'],
        ];
    }
}
