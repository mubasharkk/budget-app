<?php

namespace App\Domain\Identity\Services;

use App\Models\User;

class ProfileService
{
    /**
     * Update profile details; changing the email address requires re-verification.
     *
     * @param  array{name?: string, email?: string}  $data
     */
    public function update(User $user, array $data): User
    {
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }

    /**
     * Set the currency used for the user's totals and as the default on new records.
     */
    public function updateDefaultCurrency(User $user, string $currency): User
    {
        $user->update(['default_currency' => $currency]);

        return $user;
    }

    public function deleteAccount(User $user): void
    {
        $user->delete();
    }
}
