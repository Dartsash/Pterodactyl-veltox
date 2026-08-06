<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\Addon;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Addons\AnnouncementService;
use Pterodactyl\Models\StaffRole;
use Pterodactyl\Services\Addons\StaffPermissionService;
use Pterodactyl\Services\Addons\VersionManagerService;
use Pterodactyl\Services\Addons\PluginVersionService;
use Pterodactyl\Services\Addons\PlayerManagerService;
use Pterodactyl\Services\Addons\ModInstallerService;
use Pterodactyl\Services\Servers\ServerPropertiesService;
use Pterodactyl\Services\Servers\StartupCommandBuilderService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class AddonController extends Controller
{
    protected const CATEGORIES = ['Plugin', 'Mod', 'Datapack'];

    /**
     * Persistent setting holding the on/off state of the whole Plugin Manager
     * addon. When disabled the marketplace disappears for every client server.
     */
    protected const SETTING_PLUGIN_MANAGER = 'settings::addons:plugin_manager_enabled';

    /**
     * Labels and help text for every option the Startup Editor addon can offer.
     */
    protected const STARTUP_OPTIONS = [
        'memory' => ['Heap size (RAM)', 'Lets the owner pin -Xms/-Xmx. Always clamped to the server memory limit.'],
        'aikar' => ['Optimization flags (Aikar)', "Adds Aikar's recommended G1GC tuning flags."],
        'ignore_java_version' => ['Ignore Java version check', 'Adds -DPaper.IgnoreJavaVersion=true before -jar.'],
        'utf8' => ['UTF-8 encoding', 'Adds -Dfile.encoding=UTF-8.'],
        'console_compat' => ['Console compatibility', 'Adds -Dterminal.jline=false -Dterminal.ansi=true.'],
        'nogui' => ['Disable GUI', 'Appends --nogui after the jar file.'],
    ];

    public function __construct(
        protected AlertsMessageBag $alert,
        protected ViewFactory $view,
        protected SettingsRepositoryInterface $settings,
        protected StartupCommandBuilderService $startupEditor,
        protected ServerPropertiesService $configEditor,
        protected AnnouncementService $announcement,
        protected StaffPermissionService $permissions,
        protected VersionManagerService $versions,
        protected PluginVersionService $pluginVersions,
        protected PlayerManagerService $players,
        protected ModInstallerService $mods,
    ) {
    }

    public function index(): View
    {
        $managers = [
            [
                'name' => 'Plugin Manager',
                'description' => 'Browse, add and toggle the plugins, mods and datapacks offered in the server marketplace.',
                'icon' => 'fa-puzzle-piece',
                'enabled' => Addon::query()->where('enabled', true)->count(),
                'total' => Addon::query()->count(),
                'url' => route('admin.addons.manage'),
                'active' => $this->managerEnabled(),
                'toggle_url' => route('admin.addons.manager.toggle', 'plugin-manager'),
            ],
            [
                'name' => 'Startup Editor',
                'description' => 'Let server owners tune their startup command using safe, whitelisted Java flags.',
                'icon' => 'fa-terminal',
                'enabled' => count($this->startupEditor->enabledOptions()),
                'total' => count(StartupCommandBuilderService::AVAILABLE_OPTIONS),
                'url' => route('admin.addons.startup'),
                'active' => $this->startupEditor->addonEnabled(),
                'toggle_url' => route('admin.addons.manager.toggle', 'startup-editor'),
            ],
            [
                'name' => 'Config Editor',
                'description' => 'Gives server owners a friendly editor for server.properties instead of raw file editing.',
                'icon' => 'fa-sliders',
                'enabled' => count($this->configEditor->enabledFields()),
                'total' => count(ServerPropertiesService::FIELDS),
                'url' => route('admin.addons.config'),
                'active' => $this->configEditor->addonEnabled(),
                'toggle_url' => route('admin.addons.manager.toggle', 'config-editor'),
            ],
            [
                'name' => 'Announcement',
                'description' => 'Show a custom notification above the greeting on every client dashboard.',
                'icon' => 'fa-bullhorn',
                'enabled' => $this->announcement->toArray(true) === null ? 0 : 1,
                'total' => 1,
                'url' => route('admin.addons.announcement'),
                'active' => $this->announcement->addonEnabled(),
                'toggle_url' => route('admin.addons.manager.toggle', 'announcement'),
            ],
            [
                'name' => 'Permission Management',
                'description' => 'Create staff roles that unlock only parts of the admin area, without handing out full administrator access.',
                'icon' => 'fa-shield',
                'enabled' => StaffRole::query()->count(),
                'total' => count(StaffPermissionService::SECTIONS),
                'url' => route('admin.addons.permissions'),
                'active' => $this->permissions->addonEnabled(),
                'toggle_url' => route('admin.addons.manager.toggle', 'permissions'),
            ],
            [
                'name' => 'Version Manager',
                'description' => 'Install Paper, Purpur, Fabric, Forge, Vanilla, proxies and more straight from the server page, with version and build picking.',
                'icon' => 'fa-cubes',
                'enabled' => count($this->versions->enabledCoreKeys()),
                'total' => count(VersionManagerService::CORES),
                'url' => route('admin.addons.versions'),
                'active' => $this->versions->addonEnabled(),
                'toggle_url' => route('admin.addons.manager.toggle', 'versions'),
            ],
            [
                'name' => 'Mod Installer',
                'description' => 'Install Fabric, Forge, NeoForge and Quilt mods from Modrinth straight from the server page.',
                'icon' => 'fa-puzzle-piece',
                'enabled' => count(ModInstallerService::LOADERS),
                'total' => count(ModInstallerService::LOADERS),
                'url' => route('admin.addons.mods'),
                'active' => $this->mods->addonEnabled(),
                'toggle_url' => route('admin.addons.manager.toggle', 'mods'),
            ],
            [
                'name' => 'Minecraft Player Manager',
                'description' => 'Manage the whitelist, operators, banned players and banned IPs from the server page instead of editing JSON files by hand.',
                'icon' => 'fa-gavel',
                'enabled' => count($this->players->enabledListKeys()),
                'total' => count(PlayerManagerService::LISTS),
                'url' => route('admin.addons.players'),
                'active' => $this->players->addonEnabled(),
                'toggle_url' => route('admin.addons.manager.toggle', 'players'),
            ],
        ];

        return view('admin.addons.index', ['managers' => $managers]);
    }

    public function manage(Request $request): View
    {
        $all = Addon::query()->orderBy('name')->get();

        $category = $request->query('category');
        $activeCategory = in_array($category, self::CATEGORIES, true) ? $category : 'All';

        $addons = $activeCategory === 'All'
            ? $all
            : $all->where('category', $activeCategory)->values();

        return view('admin.addons.manage', [
            'addons' => $addons,
            'activeCategory' => $activeCategory,
            'counts' => [
                'All' => $all->count(),
                'Plugin' => $all->where('category', 'Plugin')->count(),
                'Mod' => $all->where('category', 'Mod')->count(),
                'Datapack' => $all->where('category', 'Datapack')->count(),
            ],
            'enabledCount' => $all->where('enabled', true)->count(),
            'disabledCount' => $all->where('enabled', false)->count(),
        ]);
    }

    public function view(Addon $addon): View
    {
        return view('admin.addons.view', ['addon' => $addon]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['enabled'] = true;

        $addon = Addon::create($data);

        $this->alert->success('Addon "' . $addon->name . '" has been created.')->flash();

        return redirect()->route('admin.addons.view', $addon->id);
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        // Forget the cached version list of the OLD source before switching.
        $this->pluginVersions->flushCache($addon);

        $addon->update($this->validated($request));

        $this->pluginVersions->flushCache($addon->refresh());

        $this->alert->success('Addon "' . $addon->name . '" has been updated.')->flash();

        return redirect()->route('admin.addons.view', $addon->id);
    }

    public function delete(Addon $addon): RedirectResponse
    {
        $name = $addon->name;
        $addon->delete();

        $this->alert->success('Addon "' . $name . '" has been deleted.')->flash();

        return redirect()->route('admin.addons.manage');
    }

    public function toggle(Addon $addon): RedirectResponse
    {
        $addon->update(['enabled' => !$addon->enabled]);

        $this->alert->success(sprintf('Addon "%s" has been %s.', $addon->name, $addon->enabled ? 'enabled' : 'disabled'))->flash();

        return redirect()->route('admin.addons.manage', $addon->category === 'Plugin' ? [] : ['category' => $addon->category]);
    }

    /**
     * Settings screen for the Startup Editor addon.
     */
    public function startupEditor(): View
    {
        $enabledOptions = array_map('strval', $this->startupEditor->enabledOptions());
        $checked = [];

        foreach (self::STARTUP_OPTIONS as $key => $option) {
            $checked[$key] = in_array((string) $key, $enabledOptions);
        }

        return view('admin.addons.startup', [
            'active' => $this->startupEditor->addonEnabled(),
            'manual' => $this->startupEditor->manualAllowed(),
            'enabledOptions' => $enabledOptions,
            'checked' => $checked,
            'enabledCount' => count(array_filter($checked)),
            'totalCount' => count(self::STARTUP_OPTIONS),
            'options' => self::STARTUP_OPTIONS,
        ]);
    }

    /**
     * Settings screen for the Config Editor addon.
     */
    public function configEditor(): View
    {
        $available = array_keys(ServerPropertiesService::FIELDS);
        $enabled = $this->configEditor->enabledFields();

        // Resolve the checked state here so the view never has to think.
        $checked = [];
        foreach ($available as $key) {
            $checked[$key] = in_array($key, $enabled);
        }

        return view('admin.addons.config', [
            'active' => $this->configEditor->addonEnabled(),
            'checked' => $checked,
            'enabledCount' => count($enabled),
            'totalCount' => count($available),
            'fields' => ServerPropertiesService::FIELDS,
            'groups' => ServerPropertiesService::GROUPS,
        ]);
    }

    /**
     * Persist the Config Editor settings.
     */
    public function updateConfigEditor(Request $request): RedirectResponse
    {
        $available = array_keys(ServerPropertiesService::FIELDS);

        $request->validate(['fields' => 'nullable|array']);

        $submitted = $request->input('fields', []);

        if (!is_array($submitted)) {
            $submitted = [$submitted];
        }

        $selected = [];

        foreach ($submitted as $key => $value) {
            // Dropdown form posts fields[<key>] = "1" | "0".
            if (is_string($key)) {
                if (in_array($key, $available, true) && (string) $value === '1') {
                    $selected[] = $key;
                }

                continue;
            }

            // Legacy checkbox form posts fields[] = <key>.
            if (in_array((string) $value, $available, true)) {
                $selected[] = (string) $value;
            }
        }

        $selected = array_values(array_intersect($available, array_unique($selected)));

        $this->settings->set(ServerPropertiesService::SETTING_ENABLED, $request->boolean('enabled') ? '1' : '0');
        $this->settings->set(ServerPropertiesService::SETTING_FIELDS, json_encode($selected));

        $this->alert->success('The Config Editor settings have been updated.')->flash();

        return redirect()->route('admin.addons.config');
    }

    /**
     * Persist the Startup Editor settings.
     */
    public function updateStartupEditor(Request $request): RedirectResponse
    {
        $request->validate([
            'options' => 'nullable|array',
        ]);

        $submitted = $request->input('options', []);

        if (!is_array($submitted)) {
            $submitted = [];
        }

        $chosen = [];

        foreach ($submitted as $key => $value) {
            // Legacy checkbox form posts options[] = <key>, the current form posts
            // options[<key>] = 0|1 using select dropdowns. Accept both shapes.
            if (is_int($key)) {
                $chosen[] = (string) $value;
            } elseif ((string) $value === '1') {
                $chosen[] = (string) $key;
            }
        }

        $selected = array_values(array_intersect(
            StartupCommandBuilderService::AVAILABLE_OPTIONS,
            $chosen
        ));

        $this->settings->set(StartupCommandBuilderService::SETTING_ENABLED, $request->boolean('enabled') ? '1' : '0');
        $this->settings->set(StartupCommandBuilderService::SETTING_MANUAL, $request->boolean('manual') ? '1' : '0');
        $this->settings->set(StartupCommandBuilderService::SETTING_OPTIONS, json_encode($selected));

        $this->alert->success('The Startup Editor settings have been updated.')->flash();

        return redirect()->route('admin.addons.startup');
    }

    /**
     * Settings screen for the Version Manager addon.
     */
    public function versions(): View
    {
        return view('admin.addons.versions', [
            'active' => $this->versions->addonEnabled(),
            'cores' => VersionManagerService::CORES,
            'categories' => VersionManagerService::CATEGORIES,
            'enabled' => $this->versions->enabledCoreKeys(),
        ]);
    }

    /**
     * Persist which cores users may install.
     */
    public function updateVersions(Request $request): RedirectResponse
    {
        if ($request->input('flush')) {
            $this->versions->flushCache();

            $this->alert->success('Cached version listings have been cleared.')->flash();

            return redirect()->route('admin.addons.versions');
        }

        $submitted = (array) $request->input('cores', []);
        $keys = [];

        foreach ($submitted as $key => $value) {
            if ((string) $value === '1') {
                $keys[] = (string) $key;
            }
        }

        $this->versions->saveEnabledCores($keys);

        $this->alert->success('Version Manager has been updated.')->flash();

        return redirect()->route('admin.addons.versions');
    }

    /**
     * Settings screen for the Mod Installer addon.
     */
    public function mods(): View
    {
        return view('admin.addons.mods', [
            'active' => $this->mods->addonEnabled(),
            'limit' => $this->mods->resultLimit(),
            'allowClient' => $this->mods->allowClientMods(),
        ]);
    }

    /**
     * Persist how the Mod Installer searches Modrinth.
     */
    public function updateMods(Request $request): RedirectResponse
    {
        $limit = (int) $request->input('limit', 20);
        $limit = max(5, min(50, $limit));

        $this->settings->set(ModInstallerService::SETTING_LIMIT, (string) $limit);
        $this->settings->set(
            ModInstallerService::SETTING_ALLOW_CLIENT,
            $request->boolean('client') ? '1' : '0'
        );

        // Cached search responses were built with the old filters, so they would
        // keep hiding or showing client mods until they expired on their own.
        $this->mods->flushCache();

        $this->alert->success('Mod Installer has been updated.')->flash();

        return redirect()->route('admin.addons.mods');
    }

    /**
     * Settings screen for the Minecraft Player Manager addon.
     */
    public function players(): View
    {
        return view('admin.addons.players', [
            'active' => $this->players->addonEnabled(),
            'lists' => PlayerManagerService::LISTS,
            'enabled' => $this->players->enabledListKeys(),
            'lookup' => $this->players->lookupEnabled(),
        ]);
    }

    /**
     * Persist which player lists users may manage.
     */
    public function updatePlayers(Request $request): RedirectResponse
    {
        $submitted = (array) $request->input('lists', []);
        $keys = [];

        foreach ($submitted as $key => $value) {
            if ((string) $value === '1') {
                $keys[] = (string) $key;
            }
        }

        $this->players->setEnabledListKeys($keys);

        $this->settings->set(
            PlayerManagerService::SETTING_LOOKUP,
            $request->boolean('lookup') ? '1' : '0'
        );

        $this->alert->success('Minecraft Player Manager has been updated.')->flash();

        return redirect()->route('admin.addons.players');
    }

    /**
     * Settings screen for the Announcement addon.
     */
    public function announcement(): View
    {
        return view('admin.addons.announcement', [
            'active' => $this->announcement->addonEnabled(),
            'title' => $this->announcement->title(),
            'message' => $this->announcement->message(),
            'type' => $this->announcement->type(),
            'types' => AnnouncementService::TYPES,
            'dismissible' => $this->announcement->dismissible(),
            'adminOnly' => $this->announcement->adminOnly(),
        ]);
    }

    /**
     * Persist the Announcement addon settings.
     */
    public function updateAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:120',
            'message' => 'nullable|string|max:2000',
            'type' => 'required|in:' . implode(',', array_keys(AnnouncementService::TYPES)),
        ]);

        $this->announcement->save([
            'enabled' => $request->boolean('enabled'),
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'type' => $data['type'],
            'dismissible' => $request->boolean('dismissible'),
            'admin_only' => $request->boolean('admin_only'),
        ]);

        $this->alert->success('The announcement has been updated.')->flash();

        return redirect()->route('admin.addons.announcement');
    }

    /**
     * Turn an entire addon on or off from the addon list.
     */
    public function toggleManager(string $manager): RedirectResponse
    {
        abort_unless(in_array($manager, ['plugin-manager', 'startup-editor', 'config-editor', 'announcement', 'permissions', 'versions', 'players', 'mods'], true), 404);

        if ($manager === 'mods') {
            $enabled = !$this->mods->addonEnabled();
            $this->settings->set(ModInstallerService::SETTING_ENABLED, $enabled ? '1' : '0');
            $name = 'Mod Installer';
        } elseif ($manager === 'players') {
            $enabled = !$this->players->addonEnabled();
            $this->settings->set(PlayerManagerService::SETTING_ENABLED, $enabled ? '1' : '0');
            $name = 'Minecraft Player Manager';
        } elseif ($manager === 'versions') {
            $enabled = !$this->versions->addonEnabled();
            $this->settings->set(VersionManagerService::SETTING_ENABLED, $enabled ? '1' : '0');
            $name = 'Version Manager';
        } elseif ($manager === 'permissions') {
            $enabled = !$this->permissions->addonEnabled();
            $this->settings->set(StaffPermissionService::SETTING_ENABLED, $enabled ? '1' : '0');
            $name = 'Permission Management';
        } elseif ($manager === 'announcement') {
            $enabled = !$this->announcement->addonEnabled();
            $this->settings->set(AnnouncementService::SETTING_ENABLED, $enabled ? '1' : '0');
            $name = 'Announcement';
        } elseif ($manager === 'startup-editor') {
            $enabled = !$this->startupEditor->addonEnabled();
            $this->settings->set(StartupCommandBuilderService::SETTING_ENABLED, $enabled ? '1' : '0');
            $name = 'Startup Editor';
        } elseif ($manager === 'config-editor') {
            $enabled = !$this->configEditor->addonEnabled();
            $this->settings->set(ServerPropertiesService::SETTING_ENABLED, $enabled ? '1' : '0');
            $name = 'Config Editor';
        } else {
            $enabled = !$this->managerEnabled();
            $this->settings->set(self::SETTING_PLUGIN_MANAGER, $enabled ? '1' : '0');
            $name = 'Plugin Manager';
        }

        $this->alert->success(sprintf(
            'The %s addon has been %s.',
            $name,
            $enabled ? 'enabled' : 'disabled'
        ))->flash();

        return redirect()->route('admin.addons');
    }

    /**
     * Whether the Plugin Manager addon is currently active. Defaults to on.
     */
    protected function managerEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING_PLUGIN_MANAGER, '1');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'author' => 'nullable|string|max:191',
            'category' => 'required|in:Plugin,Mod,Datapack',
            'description' => 'nullable|string|max:500',
            'version' => 'nullable|string|max:64',
            'source' => 'required|in:' . implode(',', PluginVersionService::SOURCES),
            'source_id' => 'nullable|string|max:191',
            // A static addon has nothing to resolve, so it must carry a link.
            // API backed addons build their URL per version at install time.
            'url' => 'nullable|url|max:2048|required_if:source,static',
            'filename' => 'required|string|max:191',
            'downloads' => 'nullable|string|max:32',
            'rating' => 'nullable|numeric|min:0|max:5',
        ], [
            'url.required_if' => 'A download URL is required when the release source is a static link.',
        ]);

        $data['source'] = $data['source'] ?? PluginVersionService::SOURCE_STATIC;
        $data['source_id'] = trim((string) ($data['source_id'] ?? '')) ?: null;
        $data['url'] = trim((string) ($data['url'] ?? '')) ?: null;

        // A non-static source without a project id could never resolve anything.
        if ($data['source'] !== PluginVersionService::SOURCE_STATIC && $data['source_id'] === null) {
            $data['source'] = PluginVersionService::SOURCE_STATIC;
        }

        $data['rating'] = $data['rating'] ?? 5.0;
        $data['author'] = $data['author'] ?? null;
        $data['downloads'] = $data['downloads'] ?? null;
        $data['description'] = $data['description'] ?? null;
        $data['version'] = $data['version'] ?? null;

        return $data;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'addon';
        $slug = $base;
        $i = 1;
        while (Addon::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
