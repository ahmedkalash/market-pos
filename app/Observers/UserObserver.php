<?php

namespace App\Observers;

use App\Events\UserTransferred;
use App\Models\Store;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     *
     * We use this hook to detect when a user is "transferred" between stores.
     * A transfer is defined as a change in 'store_id' where the original value was not null.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('store_id')) {
            $oldStoreId = $user->getOriginal('store_id');
            $newStoreId = $user->store_id;

            // Rule: Only fire if it was a real transfer (old store was not null)
            // and the store actually changed to a different value.
            if ($oldStoreId !== null && $oldStoreId !== $newStoreId) {
                UserTransferred::dispatch(
                    $user,
                    Store::find($oldStoreId),
                    $user->store
                );
            }
        }
    }
}
