<?php

namespace Pterodactyl\Services\Addons;

use Pterodactyl\Models\User;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Backs the "Permission Management" addon.
 *
 * Maps admin routes onto coarse sections (Servers, Users, Nodes, ...) so a
 * staff role can be allowed into some parts of /admin without being handed a
 * full root administrator account.
 */
class StaffPermissionService
{
    public const SETTING_ENABLED = 'settings::addons:permissions_enabled';

    /**
     * Every section a staff role may be granted. The key is stored inside the
     * role permissions array, the route prefix is what the middleware matches
     * an incoming request against.
     */
    public const SECTIONS = [
        'servers' => ['label' => 'Servers', 'icon' => 'fa-server', 'route' => 'admin.servers', 'description' => 'View and manage every server on the panel.'],
        'users' => ['label' => 'Users', 'icon' => 'fa-users', 'route' => 'admin.users', 'description' => 'View and manage user accounts. Administrator accounts stay protected.'],
        'nodes' => ['label' => 'Nodes', 'icon' => 'fa-sitemap', 'route' => 'admin.nodes', 'description' => 'Manage nodes and their allocations.'],
        'locations' => ['label' => 'Locations', 'icon' => 'fa-globe', 'route' => 'admin.locations', 'description' => 'Manage locations.'],
        'databases' => ['label' => 'Databases', 'icon' => 'fa-database', 'route' => 'admin.databases', 'description' => 'Manage database hosts.'],
        'nests' => ['label' => 'Nests & Eggs', 'icon' => 'fa-th-large', 'route' => 'admin.nests', 'description' => 'Manage nests, eggs and their variables.'],
        'mounts' => ['label' => 'Mounts', 'icon' => 'fa-magic', 'route' => 'admin.mounts', 'description' => 'Manage mounts.'],
        'addons' => ['label' => 'Addons', 'icon' => 'fa-puzzle-piece', 'route' => 'admin.addons', 'description' => 'Manage addons. The Permission Management screen itself stays root only.'],
        'settings' => ['label' => 'Settings', 'icon' => 'fa-wrench', 'route' => 'admin.settings', 'description' => 'Panel settings, mail and branding. Grant with care.'],
        'api' => ['label' => 'Application API', 'icon' => 'fa-gamepad', 'route' => 'admin.api', 'description' => 'Create and revoke application API keys. Grant with care.'],
    ];

    /**
     * Routes only a root administrator may ever open, even if the matching
     * section was granted to the role.
     */
    public const ROOT_ONLY_ROUTES = ['admin.addons.permissions'];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * Whether the addon is switched on. Enabled by default; when disabled only
     * root administrators can reach /admin, exactly like stock Pterodactyl.
     */
    public function addonEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING_ENABLED, '1');
    }

    /**
     * Resolve which section an admin route name belongs to.
     */
    public function sectionForRoute(?string $routeName): ?string
    {
        if (empty($routeName)) {
            return null;
        }

        foreach (self::SECTIONS as $key => $section) {
            if ($routeName === $section['route'] || str_starts_with($routeName, $section['route'] . '.')) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Routes reserved for root administrators.
     */
    public function isRootOnlyRoute(?string $routeName): bool
    {
        if (empty($routeName)) {
            return false;
        }

        foreach (self::ROOT_ONLY_ROUTES as $prefix) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this user is a staff member with a role attached.
     */
    public function isStaff(?User $user): bool
    {
        return $user !== null && !$user->root_admin && $this->addonEnabled() && $user->staffRole !== null;
    }

    /**
     * Whether the user may open the given section. Root administrators always
     * can, staff members only when their role allows it.
     */
    public function userCan(?User $user, string $section): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->root_admin) {
            return true;
        }

        return $this->isStaff($user) && $user->staffRole->hasPermission($section);
    }

    /**
     * Whether the user may open any server in the client area, exactly like a
     * root administrator. Tied to the "Servers" section, because a staff member
     * who can manage every server from /admin needs to be able to open its
     * console, files and settings too.
     */
    public function canManageServers(?User $user): bool
    {
        return $this->userCan($user, 'servers');
    }

    /**
     * Whether the user is a staff member (not a root admin) allowed to manage
     * every server on the panel.
     */
    public function isServerStaff(?User $user): bool
    {
        return $this->isStaff($user) && $user->staffRole->hasPermission('servers');
    }

    /**
     * Whether the user may see the admin area at all.
     */
    public function canAccessAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->root_admin) {
            return true;
        }

        return $this->isStaff($user) && count($user->staffRole->permissions ?? []) > 0;
    }
}
