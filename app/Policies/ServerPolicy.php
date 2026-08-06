<?php

namespace Pterodactyl\Policies;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Addons\StaffPermissionService;

class ServerPolicy
{
    public function __construct(private StaffPermissionService $staff)
    {
    }

    /**
     * Checks if the user has the given permission on/for the server.
     */
    protected function checkPermission(User $user, Server $server, string $permission): bool
    {
        $subuser = $server->subusers->where('user_id', $user->id)->first();
        if (!$subuser || empty($permission)) {
            return false;
        }

        return in_array($permission, $subuser->permissions);
    }

    /**
     * Runs before any of the functions are called. Used to determine if user is root admin, if so, ignore permissions.
     */
    public function before(User $user, string $ability, Server $server): bool
    {
        // Staff roles holding the "Servers" section manage every server, so
        // they bypass the per server permission checks like a root admin does.
        if ($user->root_admin || $server->owner_id === $user->id || $this->staff->canManageServers($user)) {
            return true;
        }

        return $this->checkPermission($user, $server, $ability);
    }

    /**
     * This is a horrendous hack to avoid Laravel's "smart" behavior that does
     * not call the before() function if there isn't a function matching the
     * policy permission.
     */
    public function __call(string $name, mixed $arguments)
    {
        // do nothing
    }
}
