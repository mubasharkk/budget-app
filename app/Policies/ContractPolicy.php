<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function view(User $user, Contract $contract): bool
    {
        return $contract->user_id === $user->id;
    }

    public function update(User $user, Contract $contract): bool
    {
        return $contract->user_id === $user->id;
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $contract->user_id === $user->id;
    }

    public function markPaid(User $user, Contract $contract): bool
    {
        return $contract->user_id === $user->id;
    }
}
