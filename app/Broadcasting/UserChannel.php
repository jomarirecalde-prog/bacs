<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * Authorises the private user channel used for in-app notifications and
 * live calendar updates. A subscriber may only join their own channel.
 */
class UserChannel
{
    public function join(User $user, int $id): bool
    {
        return (int) $user->id === (int) $id && $user->isActive();
    }
}
