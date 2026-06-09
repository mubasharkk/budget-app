<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;

class ReceiptPolicy
{
    /**
     * Determine whether the user can view the receipt.
     */
    public function view(User $user, Receipt $receipt): bool
    {
        return $this->owns($user, $receipt);
    }

    /**
     * Determine whether the user can update the receipt.
     */
    public function update(User $user, Receipt $receipt): bool
    {
        return $this->owns($user, $receipt);
    }

    /**
     * Determine whether the user can delete the receipt.
     */
    public function delete(User $user, Receipt $receipt): bool
    {
        return $this->owns($user, $receipt);
    }

    /**
     * Determine whether the user can retry processing the receipt.
     */
    public function retry(User $user, Receipt $receipt): bool
    {
        return $this->owns($user, $receipt);
    }

    private function owns(User $user, Receipt $receipt): bool
    {
        return $receipt->user_id === $user->id;
    }
}
