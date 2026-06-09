<?php

namespace App\Http\Controllers;

use App\Enums\IncomeType;
use App\Http\Requests\IncomeUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'incomeTypeOptions' => IncomeType::options(),
        ]);
    }

    public function updateIncome(IncomeUpdateRequest $request): RedirectResponse
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

        return Redirect::route('profile.edit')->with('status', 'income-updated');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
