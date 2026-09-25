<?php

namespace App\Http\Controllers;

use App\Domain\Savings\Services\SavingService;
use App\Domain\Shared\Support\SupportedCurrencies;
use App\Http\Requests\SavingRequest;
use App\Models\Saving;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavingController extends Controller
{
    public function __construct(private SavingService $savingService) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        return Inertia::render('Savings/Index', [
            'savings' => $this->savingService->listForUser($userId),
            'summary' => $this->savingService->summary($userId),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Savings/Create', $this->formOptions());
    }

    public function store(SavingRequest $request): RedirectResponse
    {
        $this->savingService->create($request->user(), $request->validated());

        return redirect()->route('savings.index')
            ->with('success', 'Savings recorded successfully.');
    }

    public function edit(Saving $saving): Response
    {
        $this->authorize('update', $saving);

        return Inertia::render('Savings/Edit', [
            'saving' => $saving,
            ...$this->formOptions(),
        ]);
    }

    public function update(SavingRequest $request, Saving $saving): RedirectResponse
    {
        $this->authorize('update', $saving);

        $this->savingService->update($saving, $request->validated());

        return redirect()->route('savings.index')
            ->with('success', 'Savings updated successfully.');
    }

    public function destroy(Saving $saving): RedirectResponse
    {
        $this->authorize('delete', $saving);

        $this->savingService->delete($saving);

        return redirect()->route('savings.index')
            ->with('success', 'Savings deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'currencies' => SupportedCurrencies::all(),
        ];
    }
}
