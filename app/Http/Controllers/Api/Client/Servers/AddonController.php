<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Addon;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerAddon;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Addons\PluginVersionService;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Addons\GetAddonsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Addons\DeleteAddonRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Addons\InstallAddonRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Addons\ToggleAddonStateRequest;

class AddonController extends ClientApiController
{
    /**
     * Suffix appended to an addon file to keep it on disk but stop the server
     * from loading it.
     */
    private const DISABLED_SUFFIX = '.disabled';

    public function __construct(
        private DaemonFileRepository $fileRepository,
        private SettingsRepositoryInterface $settings,
        private PluginVersionService $pluginVersions,
    ) {
        parent::__construct();
    }

    public function index(GetAddonsRequest $request, Server $server): JsonResponse
    {
        // The whole addon can be switched off from /admin/addons.
        if (!$this->pluginManagerEnabled()) {
            return new JsonResponse(['data' => []]);
        }

        $installed = $server->addons()->get()->keyBy('addon_id');

        $data = Addon::query()->where('enabled', true)->orderBy('name')->get()->map(function (Addon $addon) use ($installed) {
            $record = $installed->get($addon->slug);

            return [
                'id' => $addon->slug,
                'name' => $addon->name,
                'author' => $addon->author,
                'category' => $addon->category,
                'description' => $addon->description,
                'version' => $addon->version,
                'downloads' => $addon->downloads,
                'rating' => (float) $addon->rating,
                'installed' => !is_null($record),
                'installed_version' => $record?->version,
                // Tells the UI whether it makes sense to ask for a version list.
                'has_versions' => $this->pluginVersions->supportsVersions($addon),
                // Only meaningful once installed. Defaults to true so a freshly
                // installed addon never renders as switched off.
                'enabled' => $record?->enabled ?? true,
            ];
        })->values()->all();

        return new JsonResponse(['data' => $data]);
    }

    /**
     * Lists every version that can be installed for an addon ("Available
     * versions" in the marketplace). Falls back to the single stored download
     * link when the addon has no release source configured.
     */
    public function versions(GetAddonsRequest $request, Server $server, string $addon): JsonResponse
    {
        $entry = $this->findAddon($addon);

        $installed = ServerAddon::query()
            ->where('server_id', $server->id)
            ->where('addon_id', $entry->slug)
            ->first();

        $versions = collect($this->pluginVersions->versions($entry))->map(fn (array $version) => [
            'version' => $version['version'],
            'game_versions' => $version['game_versions'] ?? [],
            'loaders' => $version['loaders'] ?? [],
            'released' => $version['released'] ?? null,
            'prerelease' => (bool) ($version['prerelease'] ?? false),
        ])->values()->all();

        return new JsonResponse(['data' => [
            'id' => $entry->slug,
            'source' => $this->pluginVersions->supportsVersions($entry) ? $entry->source : 'static',
            'installed_version' => $installed?->version,
            'versions' => $versions,
        ]]);
    }

    public function store(InstallAddonRequest $request, Server $server, string $addon): JsonResponse
    {
        $entry = $this->findAddon($addon);
        $directory = $this->directoryFor($entry->category);

        // The client may ask for a specific version out of the list returned by
        // the versions endpoint. Anything unknown is rejected there, so a stale
        // browser tab can never make us download an arbitrary URL.
        $requested = $request->input('version');
        $resolved = $this->pluginVersions->resolve($entry, is_string($requested) ? $requested : null);
        $installedVersion = $resolved['version'];

        // Wings' remote downloader does NOT follow HTTP redirects: any 301/302/307
        // response makes it fail with "got bad response status from endpoint".
        // Most real download links (GitHub releases, dev.bukkit, etc.) redirect,
        // so we resolve the final direct URL here and hand THAT to the daemon.
        $downloadUrl = $this->resolveDownloadUrl($resolved['url']);

        try {
            $this->fileRepository->setServer($server)->pull($downloadUrl, $directory, [
                'filename' => $entry->filename,
                'foreground' => true,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Daemon refused to download an addon.', [
                'server_id' => $server->id,
                'addon' => $entry->slug,
                'url' => $downloadUrl,
                'exception' => $exception->getMessage(),
            ]);

            throw new DisplayException(sprintf(
                'The daemon could not download this addon (%s). Verify the download URL set in the admin panel is reachable from the node.',
                $exception->getMessage() ?: 'no response from daemon'
            ));
        }

        // A previous install may have left a disabled copy behind. Without this
        // the server would end up with both X.jar and X.jar.disabled.
        try {
            $this->fileRepository->setServer($server)->deleteFiles($directory, [$entry->filename . self::DISABLED_SUFFIX]);
        } catch (\Throwable $exception) {
            // Nothing to clean up, which is the normal case.
        }

        $record = ServerAddon::query()->updateOrCreate(
            ['server_id' => $server->id, 'addon_id' => $entry->slug],
            ['version' => $installedVersion, 'enabled' => true, 'installed_at' => now()],
        );

        Activity::event('server:addon.install')->subject($server)->property('addon', $entry->name)->property('version', $installedVersion)->log();

        return new JsonResponse(['data' => [
            'id' => $record->addon_id,
            'installed' => true,
            'enabled' => true,
            'installed_version' => $record->version,
        ]]);
    }

    /**
     * Enable or disable an already installed addon by renaming its file on the
     * node, so the user does not have to uninstall and redownload it.
     */
    public function state(ToggleAddonStateRequest $request, Server $server, string $addon): JsonResponse
    {
        $entry = $this->findAddon($addon);

        $record = ServerAddon::query()
            ->where('server_id', $server->id)
            ->where('addon_id', $entry->slug)
            ->first();

        if (is_null($record)) {
            throw new DisplayException('This addon is not installed on this server.');
        }

        $enabled = $request->boolean('enabled');

        if ($record->enabled === $enabled) {
            return new JsonResponse(['data' => ['id' => $record->addon_id, 'enabled' => $enabled]]);
        }

        $directory = $this->directoryFor($entry->category);
        $from = $enabled ? $entry->filename . self::DISABLED_SUFFIX : $entry->filename;
        $to = $enabled ? $entry->filename : $entry->filename . self::DISABLED_SUFFIX;

        try {
            $this->fileRepository->setServer($server)->renameFiles($directory, [['from' => $from, 'to' => $to]]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to toggle an addon file on the daemon.', [
                'server_id' => $server->id,
                'addon' => $entry->slug,
                'from' => $from,
                'to' => $to,
                'exception' => $exception->getMessage(),
            ]);

            throw new DisplayException(sprintf(
                'Could not %s this addon: "%s" could not be renamed in %s on the node.',
                $enabled ? 'enable' : 'disable',
                $from,
                $directory
            ));
        }

        $record->update(['enabled' => $enabled]);

        Activity::event($enabled ? 'server:addon.enable' : 'server:addon.disable')
            ->subject($server)
            ->property('addon', $entry->name)
            ->log();

        return new JsonResponse(['data' => ['id' => $record->addon_id, 'enabled' => $enabled]]);
    }

    public function destroy(DeleteAddonRequest $request, Server $server, string $addon): Response
    {
        $entry = Addon::query()->where('slug', $addon)->first();

        if ($entry) {
            try {
                // The file may be sitting there enabled or disabled, so remove
                // both possible names in a single call.
                $this->fileRepository->setServer($server)->deleteFiles($this->directoryFor($entry->category), [
                    $entry->filename,
                    $entry->filename . self::DISABLED_SUFFIX,
                ]);
            } catch (\Throwable $exception) {
                // The database row is still removed below, otherwise a node that
                // is temporarily down would make the addon impossible to remove
                // from the UI. Log it so the leftover file stays traceable.
                Log::warning('Failed to delete addon files from the daemon.', [
                    'server_id' => $server->id,
                    'addon' => $addon,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        ServerAddon::query()->where('server_id', $server->id)->where('addon_id', $addon)->delete();

        Activity::event('server:addon.uninstall')->subject($server)->property('addon', $entry->name ?? $addon)->log();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Follow redirects from the panel side and return the final, direct URL.
     *
     * The daemon downloader requires a URL that answers 200 immediately. This
     * method walks the redirect chain (using a browser-like User-Agent so CDNs
     * such as CurseForge/forgecdn don't block us) and returns the resolved URL.
     *
     * Every hop is validated: the first host is admin supplied, but whatever it
     * redirects to is controlled by a third party and must not be allowed to
     * point back at the panel's own network.
     */
    private function resolveDownloadUrl(string $url): string
    {
        $this->assertPublicUrl($url);

        $finalUrl = $url;

        try {
            Http::withOptions([
                'stream' => true, // don't buffer the whole jar on the panel
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true,
                    'on_redirect' => function ($request, $response, $uri) {
                        $this->assertPublicUrl((string) $uri);
                    },
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use (&$finalUrl) {
                    $finalUrl = (string) $stats->getEffectiveUri();
                },
            ])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Veltox-Panel/1.0)'])
                ->timeout(25)
                ->get($url);
        } catch (DisplayException $exception) {
            // A blocked redirect target must not be swallowed.
            throw $exception;
        } catch (\Throwable $exception) {
            // Network hiccup while resolving: fall back to the original,
            // already validated URL so behaviour never gets worse than before.
            return $url;
        }

        $this->assertPublicUrl($finalUrl);

        return $finalUrl ?: $url;
    }

    /**
     * Reject anything that is not a plain http(s) URL pointing at a public
     * address. Without this the panel is a confused deputy: it will happily
     * fetch http://127.0.0.1/, a LAN host, or a cloud metadata endpoint such as
     * http://169.254.169.254/ and surface the outcome to the caller.
     */
    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw new DisplayException('The addon download URL is not a valid URL.');
        }

        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            throw new DisplayException('Addon downloads must use http:// or https://.');
        }

        $host = $parts['host'];
        $addresses = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else {
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ip'])) {
                    $addresses[] = $record['ip'];
                }
                if (!empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        if (empty($addresses)) {
            throw new DisplayException(sprintf('The addon download host "%s" could not be resolved.', $host));
        }

        foreach ($addresses as $address) {
            // NO_PRIV_RANGE covers RFC1918 and fc00::/7, NO_RES_RANGE covers
            // loopback, link-local (including 169.254.169.254) and 0.0.0.0/8.
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new DisplayException(sprintf(
                    'The addon download URL resolves to the non-public address %s and was blocked.',
                    $address
                ));
            }
        }
    }

    /**
     * Whether the Plugin Manager addon is switched on in the admin area.
     */
    private function pluginManagerEnabled(): bool
    {
        return (bool) $this->settings->get('settings::addons:plugin_manager_enabled', '1');
    }

    private function findAddon(string $slug): Addon
    {
        if (!$this->pluginManagerEnabled()) {
            throw new DisplayException('The plugin marketplace is currently disabled by an administrator.');
        }

        $addon = Addon::query()->where('slug', $slug)->where('enabled', true)->first();

        if (is_null($addon)) {
            throw new DisplayException('The requested addon is not available.');
        }

        return $addon;
    }

    private function directoryFor(string $category): string
    {
        return match ($category) {
            'Mod' => '/mods',
            'Datapack' => '/world/datapacks',
            default => '/plugins',
        };
    }
}
