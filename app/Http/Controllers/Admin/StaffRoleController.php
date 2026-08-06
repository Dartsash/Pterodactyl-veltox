<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\StaffRole;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Addons\StaffPermissionService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Admin screens for the "Permission Management" addon.
 *
 * Endpoint: /admin/addons/permissions
 */
class StaffRoleController extends Controller
{
    public const COLORS = [
        'primary' => 'Blue',
        'success' => 'Green',
        'warning' => 'Orange',
        'danger' => 'Red',
        'default' => 'Grey',
    ];

    public function __construct(
        protected AlertsMessageBag $alert,
        protected SettingsRepositoryInterface $settings,
        protected StaffPermissionService $service,
    ) {
    }

    /**
     * List every staff role.
     */
    public function index(): View
    {
        return view('admin.addons.permissions.index', [
            'active' => $this->service->addonEnabled(),
            'roles' => StaffRole::query()->withCount('users')->orderBy('name')->get(),
            'sections' => StaffPermissionService::SECTIONS,
        ]);
    }

    /**
     * Form for a brand new role.
     */
    public function create(): View
    {
        return view('admin.addons.permissions.view', [
            'role' => new StaffRole(['color' => 'primary', 'permissions' => []]),
            'sections' => StaffPermissionService::SECTIONS,
            'colors' => self::COLORS,
            'members' => collect(),
        ]);
    }

    /**
     * Form for an existing role.
     */
    public function view(StaffRole $role): View
    {
        return view('admin.addons.permissions.view', [
            'role' => $role,
            'sections' => StaffPermissionService::SECTIONS,
            'colors' => self::COLORS,
            'members' => $role->users()->orderBy('username')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = StaffRole::create($this->validated($request));

        $this->alert->success('Staff role "' . $role->name . '" has been created.')->flash();

        return redirect()->route('admin.addons.permissions.view', $role->id);
    }

    public function update(Request $request, StaffRole $role): RedirectResponse
    {
        $role->update($this->validated($request));

        $this->alert->success('Staff role "' . $role->name . '" has been updated.')->flash();

        return redirect()->route('admin.addons.permissions.view', $role->id);
    }

    /**
     * Delete a role. Everybody holding it drops back to a normal user.
     */
    public function delete(StaffRole $role): RedirectResponse
    {
        $name = $role->name;

        User::query()->where('staff_role_id', $role->id)->update(['staff_role_id' => null]);
        $role->delete();

        $this->alert->success('Staff role "' . $name . '" has been deleted and unassigned from every user.')->flash();

        return redirect()->route('admin.addons.permissions');
    }

    /**
     * Turn the whole addon on or off.
     */
    public function toggle(): RedirectResponse
    {
        $enabled = !$this->service->addonEnabled();
        $this->settings->set(StaffPermissionService::SETTING_ENABLED, $enabled ? '1' : '0');

        $this->alert->success(sprintf(
            'Permission Management has been %s.%s',
            $enabled ? 'enabled' : 'disabled',
            $enabled ? '' : ' Only root administrators can reach the admin area now.'
        ))->flash();

        return redirect()->route('admin.addons.permissions');
    }

    /**
     * Validate the role form and normalise the permission checkboxes.
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'color' => 'required|in:' . implode(',', array_keys(self::COLORS)),
            'permissions' => 'nullable|array',
        ]);

        $available = array_keys(StaffPermissionService::SECTIONS);
        $submitted = $request->input('permissions', []);
        $selected = [];

        foreach (is_array($submitted) ? $submitted : [] as $key => $value) {
            // The form posts permissions[<section>] = "1" | "0".
            if (is_string($key)) {
                if (in_array($key, $available, true) && (string) $value === '1') {
                    $selected[] = $key;
                }

                continue;
            }

            if (in_array((string) $value, $available, true)) {
                $selected[] = (string) $value;
            }
        }

        return [
            'name' => $data['name'],
            'color' => $data['color'],
            'permissions' => array_values(array_intersect($available, array_unique($selected))),
        ];
    }
}
