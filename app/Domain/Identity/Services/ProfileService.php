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

    public function deleteAccount(User $user): void
    {
        $user->delete();
    }
}
