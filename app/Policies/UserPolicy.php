<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the authenticated user may assign a role to another user.
     */
    public function assignRole(User $user, User $model): bool
    {
        return $user->hasPermissionTo('system.users.assign-role');
    }
}
