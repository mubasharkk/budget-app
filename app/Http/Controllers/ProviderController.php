<?php

namespace App\Http\Controllers;

use App\Domain\Contracts\Services\ProviderService;
use App\Http\Requests\ProviderRequest;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function __construct(private ProviderService $providerService) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Providers/Index', [
            'providers' => $this->providerService->listForUser($request->user()->id),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Providers/Create');
    }

    public function store(ProviderRequest $request): RedirectResponse
    {
        $this->providerService->create($request->user(), $request->validated());

        return redirect()->route('providers.index')
            ->with('success', 'Provider created successfully.');
    }

    public function edit(Provider $provider): Response
    {
        $this->authorize('update', $provider);

        return Inertia::render('Providers/Edit', [
            'provider' => $provider,
        ]);
    }

    public function update(ProviderRequest $request, Provider $provider): RedirectResponse
    {
        $this->authorize('update', $provider);

        $this->providerService->update($provider, $request->validated());

        return redirect()->route('providers.index')
            ->with('success', 'Provider updated successfully.');
    }

    public function destroy(Provider $provider): RedirectResponse
    {
        $this->authorize('delete', $provider);

        $this->providerService->delete($provider);

        return redirect()->route('providers.index')
            ->with('success', 'Provider deleted successfully.');
    }
}
