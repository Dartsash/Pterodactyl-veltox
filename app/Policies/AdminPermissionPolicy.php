<?php

namespace Pterodactyl\Policies;

use Pterodactyl\Models\AdminPermission;
use Pterodactyl\Models\User;

class AdminPermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_root_admin === 1 || $user->hasAdminPermission('manage.permissions');
    }

    public function view(User $user, AdminPermission $permission): bool
    {
        return $user->is_root_admin === 1 || $user->hasAdminPermission('manage.permissions');
    }

    public function create(User $user): bool
    {
        return $user->is_root_admin === 1;
    }

    public function update(User $user, AdminPermission $permission): bool
    {
        return $user->is_root_admin === 1;
    }

    public function delete(User $user, AdminPermission $permission): bool
    {
        return $user->is_root_admin === 1;
    }
}
