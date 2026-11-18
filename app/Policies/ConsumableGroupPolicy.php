<?php

namespace App\Policies;

use App\Models\ConsumableGroup;
use App\Models\User;

class ConsumableGroupPolicy
{
    public function view(User $user, ConsumableGroup $group): bool
    {
        return $group->user_id === $user->id;
    }

    public function update(User $user, ConsumableGroup $group): bool
    {
        return $group->user_id === $user->id;
    }
}
