<?php

namespace App\Policies;

use App\Models\Income;
use App\Models\User;

class IncomePolicy
{
    public function view(User $user, Income $income): bool
    {
        return $income->user_id === $user->id;
    }

    public function update(User $user, Income $income): bool
    {
        return $income->user_id === $user->id;
    }

    public function delete(User $user, Income $income): bool
    {
        return $income->user_id === $user->id;
    }
}
