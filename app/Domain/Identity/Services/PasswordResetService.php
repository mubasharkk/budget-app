<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Sets a new password after it was forgotten or reset by an admin, and ends
 * every way in that relied on the old one: "remember me" cookies and API
 * tokens here, browser sessions through the AuthenticateSession middleware
 * (it signs out any session whose stored password hash no longer matches).
 */
class PasswordResetService
{
    public function reset(User $user, string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();
    }
}
