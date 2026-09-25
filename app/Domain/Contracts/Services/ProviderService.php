<?php

namespace App\Domain\Contracts\Services;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Collection;

class ProviderService
{
    /**
     * The user's providers with their contract counts.
     *
     * @return Collection<int, Provider>
     */
    public function listForUser(int $userId): Collection
    {
        return Provider::query()
            ->where('user_id', $userId)
            ->withCount('contracts')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Provider>
     */
    public function options(int $userId): Collection
    {
        return Provider::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Provider
    {
        return $user->providers()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Provider $provider, array $data): Provider
    {
        $provider->update($data);

        return $provider;
    }

    public function delete(Provider $provider): void
    {
        $provider->delete();
    }
}
