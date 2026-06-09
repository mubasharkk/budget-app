<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderRequest;
use App\Models\Provider;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function index(): Response
    {
        $providers = Provider::query()
            ->where('user_id', Auth::id())
            ->withCount('contracts')
            ->orderBy('name')
            ->get();

        return Inertia::render('Providers/Index', [
            'providers' => $providers,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Providers/Create');
    }

    public function store(ProviderRequest $request)
    {
        Auth::user()->providers()->create($request->validated());

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

    public function update(ProviderRequest $request, Provider $provider)
    {
        $this->authorize('update', $provider);

        $provider->update($request->validated());

        return redirect()->route('providers.index')
            ->with('success', 'Provider updated successfully.');
    }

    public function destroy(Provider $provider)
    {
        $this->authorize('delete', $provider);

        $provider->delete();

        return redirect()->route('providers.index')
            ->with('success', 'Provider deleted successfully.');
    }
}
