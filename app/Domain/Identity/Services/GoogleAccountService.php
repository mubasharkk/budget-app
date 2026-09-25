<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleAccountService
{
    /**
     * Find the user linked to this Google account, linking an existing account by
     * email or registering a new (pre-verified) user when none exists.
     */
    public function resolveUser(SocialiteUser $googleUser): User
    {
        $linkedUser = User::query()->where('google_id', $googleUser->getId())->first();

        if ($linkedUser !== null) {
            return $linkedUser;
        }

        $existingUser = User::query()->where('email', $googleUser->getEmail())->first();

        if ($existingUser !== null) {
            $existingUser->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);

            return $existingUser;
        }

        $user = new User([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'password' => encrypt(Str::random(12)),
        ]);
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
