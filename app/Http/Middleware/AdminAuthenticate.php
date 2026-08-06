<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Pterodactyl\Services\Addons\StaffPermissionService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminAuthenticate
{
    public function __construct(private StaffPermissionService $permissions)
    {
    }

    /**
     * Handle an incoming request.
     *
     * Root administrators keep unrestricted access. With the "Permission
     * Management" addon enabled, a user holding a staff role may additionally
     * reach the admin sections that role was granted; everything else is a 403.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $user = $request->user();

        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        if ($user->root_admin) {
            return $next($request);
        }

        if (!$this->permissions->canAccessAdmin($user)) {
            throw new AccessDeniedHttpException();
        }

        $routeName = optional($request->route())->getName();

        // Managing staff roles themselves is always reserved for root admins.
        if ($this->permissions->isRootOnlyRoute($routeName)) {
            throw new AccessDeniedHttpException();
        }

        // The admin overview is safe for anyone who got this far.
        if ($routeName === 'admin.index') {
            return $next($request);
        }

        $section = $this->permissions->sectionForRoute($routeName);

        if ($section === null || !$this->permissions->userCan($user, $section)) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }
}
