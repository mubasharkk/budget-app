<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Services\ProfileService;
use App\Domain\Incomes\Services\IncomeService;
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
    public function __construct(
        private ProfileService $profileService,
        private IncomeService $incomeService,
    ) {}

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
        $this->incomeService->updateMonthlyIncome($request->user(), $request->validated());

        return Redirect::route('profile.edit')->with('status', 'income-updated');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $this->profileService->update($request->user(), $request->validated());

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

        $this->profileService->deleteAccount($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
