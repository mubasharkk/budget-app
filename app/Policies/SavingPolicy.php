<?php

namespace App\Policies;

use App\Models\Saving;
use App\Models\User;

class SavingPolicy
{
    public function view(User $user, Saving $saving): bool
    {
        return $saving->user_id === $user->id;
    }

    public function update(User $user, Saving $saving): bool
    {
        return $saving->user_id === $user->id;
    }

    public function delete(User $user, Saving $saving): bool
    {
        return $saving->user_id === $user->id;
    }
}
