<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Addons\StaffPermissionService;

class GetUserPermissionsService
{
    public function __construct(private StaffPermissionService $staff)
    {
    }

    /**
     * Returns the server specific permissions that a user has. This checks
     * if they are an admin or a subuser for the server. If no permissions are
     * found, an empty array is returned.
     */
    public function handle(Server $server, User $user): array
    {
        // Staff members granted the "Servers" section by the Permission
        // Management addon manage every server, including its console.
        $isServerStaff = $this->staff->canManageServers($user);

        if ($user->root_admin || $isServerStaff || $user->id === $server->owner_id) {
            $permissions = ['*'];

            if ($user->root_admin || $isServerStaff) {
                $permissions[] = 'admin.websocket.errors';
                $permissions[] = 'admin.websocket.install';
                $permissions[] = 'admin.websocket.transfer';
            }

            return $permissions;
        }

        /** @var \Pterodactyl\Models\Subuser|null $subuserPermissions */
        $subuserPermissions = $server->subusers()->where('user_id', $user->id)->first();

        return $subuserPermissions ? $subuserPermissions->permissions : [];
    }
}
