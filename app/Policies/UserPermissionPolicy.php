<?php

namespace Pterodactyl\Policies;

use Pterodactyl\Models\User;
use Pterodactyl\Models\UserPermission;

class UserPermissionPolicy
{
    public function create(User $user): bool
    {
        return $user->is_root_admin === 1;
    }

    public function delete(User $user, UserPermission $userPermission): bool
    {
        return $user->is_root_admin === 1;
    }
}
