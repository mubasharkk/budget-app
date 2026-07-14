<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return $expense->user_id === $user->id;
    }

    public function update(User $user, Expense $expense): bool
    {
        return $expense->user_id === $user->id;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $expense->user_id === $user->id;
    }
}
