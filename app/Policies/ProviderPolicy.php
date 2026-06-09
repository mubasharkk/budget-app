<?php

namespace App\Policies;

use App\Models\Provider;
use App\Models\User;

class ProviderPolicy
{
    public function view(User $user, Provider $provider): bool
    {
        return $provider->user_id === $user->id;
    }

    public function update(User $user, Provider $provider): bool
    {
        return $provider->user_id === $user->id;
    }

    public function delete(User $user, Provider $provider): bool
    {
        return $provider->user_id === $user->id;
    }
}
