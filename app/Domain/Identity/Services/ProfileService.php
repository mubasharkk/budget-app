<?php

namespace App\Domain\Identity\Services;

use App\Domain\Deals\Services\DealSyncService;
use App\Jobs\SyncDealsForPostalCode;
use App\Models\User;

class ProfileService
{
    public function __construct(private DealSyncService $dealSyncService) {}

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

    /**
     * Set the postal code that regional supermarket deals are fetched for.
     * A postal code nobody has synced yet is fetched straight away instead of
     * waiting for the daily deals:sync.
     */
    public function updatePostalCode(User $user, string $postalCode): User
    {
        $user->update(['postal_code' => $postalCode]);

        if (! $this->dealSyncService->hasDealsFor($postalCode)) {
            SyncDealsForPostalCode::dispatch($postalCode);
        }

        return $user;
    }

    public function deleteAccount(User $user): void
    {
        $user->delete();
    }
}
