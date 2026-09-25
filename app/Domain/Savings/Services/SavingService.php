<?php

namespace App\Domain\Savings\Services;

use App\Models\Saving;
use App\Models\User;
use Illuminate\Support\Collection;

class SavingService
{
    /**
     * @return Collection<int, Saving>
     */
    public function listForUser(int $userId): Collection
    {
        return Saving::query()
            ->where('user_id', $userId)
            ->orderByDesc('saved_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{total: float, count: int}
     */
    public function summary(int $userId): array
    {
        $query = Saving::query()->where('user_id', $userId);

        return [
            'total' => round((float) (clone $query)->sum('amount'), 2),
            'count' => $query->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Saving
    {
        return $user->savings()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Saving $saving, array $data): Saving
    {
        $saving->update($data);

        return $saving;
    }

    public function delete(Saving $saving): void
    {
        $saving->delete();
    }
}
