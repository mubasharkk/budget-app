<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Services\GoogleAccountService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleController extends Controller
{
    /**
     * Google sign-ins stay logged in for 30 days via the remember-me cookie.
     */
    public const REMEMBER_MINUTES = 60 * 24 * 30;

    public function __construct(private GoogleAccountService $googleAccountService) {}

    public function redirectToGoogle(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $user = $this->googleAccountService->resolveUser(Socialite::driver('google')->user());
        } catch (Exception) {
            return redirect('/login')->with('error', 'Something went wrong with Google authentication.');
        }

        Auth::guard('web')->setRememberDuration(self::REMEMBER_MINUTES);
        Auth::guard('web')->login($user, remember: true);

        return redirect()->intended('/dashboard');
    }
}
