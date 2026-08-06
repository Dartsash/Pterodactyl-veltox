<?php

namespace Pterodactyl\Services\Addons;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Backs the "Mod Installer" addon.
 *
 * Searches Modrinth for mods that match the loader and game version a server is
 * actually running, and resolves the concrete jar to hand to the daemon.
 *
 * Everything here is read only against a third party API, so every response is
 * treated as untrusted and cached. A failure never throws past the controller
 * as a raw exception: the UI gets a readable message instead.
 */
class ModInstallerService
{
    public const SETTING_ENABLED = 'settings::addons:mods_enabled';

    /** How many search results a client may ask for. */
    public const SETTING_LIMIT = 'settings::addons:mods_limit';

    /** Whether users may install mods Modrinth marks as client side only. */
    public const SETTING_ALLOW_CLIENT = 'settings::addons:mods_allow_client';

    public const CACHE_TTL = 1800;

    /** Mods always live here; Forge, Fabric, NeoForge and Quilt all agree. */
    public const DIRECTORY = '/mods';

    public const DISABLED_SUFFIX = '.disabled';

    public const LOADERS = ['fabric', 'forge', 'neoforge', 'quilt'];

    public const SORTS = ['relevance', 'downloads', 'follows', 'newest', 'updated'];

    private const API = 'https://api.modrinth.com/v2';

    /**
     * Modrinth asks for a descriptive user agent and rate limits anonymous
     * traffic harder without one.
     */
    private const USER_AGENT = 'Veltox-Panel/1.0 (Pterodactyl Mod Installer)';

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function addonEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING_ENABLED, '1');
    }

    public function allowClientMods(): bool
    {
        return (bool) $this->settings->get(self::SETTING_ALLOW_CLIENT, '0');
    }

    /**
     * Clamped so a hand edited setting cannot ask Modrinth for thousands of
     * projects or for nothing at all.
     */
    public function resultLimit(): int
    {
        $limit = (int) $this->settings->get(self::SETTING_LIMIT, '20');

        return max(5, min(50, $limit === 0 ? 20 : $limit));
    }

    /**
     * Works out which loader and game version a server runs without touching
     * the daemon, using the egg name, the startup command and the egg
     * variables. Detection is a convenience: the user can always override both
     * in the UI, so a wrong guess is never fatal.
     *
     * @return array{loader: string|null, game_version: string|null, source: string|null}
     */
    public function detect(Server $server): array
    {
        $loader = null;
        $source = null;

        $haystack = strtolower(($server->egg->name ?? '') . ' ' . ($server->startup ?? ''));

        // NeoForge has to be tested before Forge: its name contains "forge".
        foreach (['neoforge', 'fabric', 'quilt', 'forge'] as $candidate) {
            if (str_contains($haystack, $candidate)) {
                $loader = $candidate;
                $source = 'egg';

                break;
            }
        }

        $version = null;

        try {
            foreach ($server->variables as $variable) {
                $name = strtoupper($variable->env_variable ?? '');

                if (!in_array($name, ['MINECRAFT_VERSION', 'MC_VERSION', 'VANILLA_VERSION', 'GAME_VERSION', 'VERSION'], true)) {
                    continue;
                }

                $value = trim((string) ($variable->server_value ?? $variable->default_value ?? ''));

                // "latest" and "recommended" are placeholders, not versions.
                if ($value === '' || !preg_match('/^1\.\d+(\.\d+)?$/', $value)) {
                    continue;
                }

                $version = $value;
                $source = $source ?? 'variable';

                break;
            }
        } catch (\Throwable $exception) {
            // A missing egg relation must not break the page.
        }

        return ['loader' => $loader, 'game_version' => $version, 'source' => $source];
    }

    /**
     * Release game versions, newest first, for the version filter.
     */
    /**
     * Second pass at detection, based on what is actually on disk. Egg names are
     * free text, so a perfectly normal modded server can be called something
     * like "Minecraft Java" and match nothing at all.
     *
     * $lister is given a path and returns the entries in it. It may throw for a
     * missing or unreadable directory; that is swallowed per path so a single
     * failure cannot take the page down.
     */
    public function refineDetection(array $detected, callable $lister): array
    {
        $loader = $detected['loader'] ?? null;
        $version = $detected['game_version'] ?? null;
        $source = $detected['source'] ?? null;

        if ($loader !== null && $version !== null) {
            return $detected;
        }

        $root = $this->listNames($lister, '/');

        foreach ($root as $name) {
            $lower = strtolower($name);

            if (!str_ends_with($lower, '.jar')) {
                continue;
            }

            // NeoForge is tested before Forge because its name contains "forge".
            $candidate = match (true) {
                str_contains($lower, 'quilt-server') => 'quilt',
                str_contains($lower, 'fabric-server') => 'fabric',
                str_starts_with($lower, 'neoforge-') => 'neoforge',
                str_starts_with($lower, 'forge-') => 'forge',
                default => null,
            };

            if ($candidate === null) {
                continue;
            }

            $loader = $loader ?? $candidate;
            $source = $source ?? 'files';

            // Both write the game version into the file name, for example
            // fabric-server-mc.1.20.1-loader.0.15.7-launcher.1.0.1.jar or
            // forge-1.20.1-47.2.0-server.jar.
            if ($version === null && preg_match('/(1\.\d+(?:\.\d+)?)/', $lower, $matches) === 1) {
                $version = $matches[1];
            }

            break;
        }

        // Modern Forge and NeoForge (1.17+) leave no jar in the root at all: the
        // loader lives under libraries/ and the server starts through run.sh.
        $lowerRoot = array_map('strtolower', $root);

        if ($loader === null && in_array('libraries', $lowerRoot, true)) {
            $vendors = array_map('strtolower', $this->listNames($lister, '/libraries/net'));

            $loader = match (true) {
                in_array('neoforged', $vendors, true) => 'neoforge',
                in_array('minecraftforge', $vendors, true) => 'forge',
                in_array('fabricmc', $vendors, true) => 'fabric',
                in_array('quiltmc', $vendors, true) => 'quilt',
                default => null,
            };

            if ($loader !== null) {
                $source = 'files';
            }
        }

        if ($version === null && in_array($loader, ['forge', 'neoforge'], true)) {
            $version = $this->versionFromLibraries($lister, $loader);
        }

        return ['loader' => $loader, 'game_version' => $version, 'source' => $source];
    }

    /**
     * Version folders under libraries/: Forge uses "1.20.1-47.2.0", while
     * NeoForge uses its own "21.1.72", whose first two parts are the game
     * version - 21.1.x means 1.21.1, and 21.0.x means plain 1.21.
     */
    private function versionFromLibraries(callable $lister, string $loader): ?string
    {
        $path = $loader === 'neoforge'
            ? '/libraries/net/neoforged/neoforge'
            : '/libraries/net/minecraftforge/forge';

        foreach ($this->listNames($lister, $path) as $name) {
            if (preg_match('/^(1\.\d+(?:\.\d+)?)-/', $name, $matches) === 1) {
                return $matches[1];
            }

            if ($loader === 'neoforge' && preg_match('/^(\d+)\.(\d+)\./', $name, $matches) === 1) {
                return $matches[2] === '0'
                    ? '1.' . $matches[1]
                    : '1.' . $matches[1] . '.' . $matches[2];
            }
        }

        return null;
    }

    /**
     * Flatten a directory listing into plain names, tolerating both Wings file
     * objects and plain strings.
     */
    private function listNames(callable $lister, string $path): array
    {
        try {
            $entries = (array) $lister($path);
        } catch (\Throwable $exception) {
            Log::debug(sprintf('Mod Installer: could not list %s: %s', $path, $exception->getMessage()));

            return [];
        }

        $names = [];

        foreach ($entries as $entry) {
            $name = is_array($entry) ? (string) ($entry['name'] ?? '') : (string) $entry;

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    public function gameVersions(): array
    {
        $payload = $this->getJson('game-versions', self::API . '/tag/game_version');

        $versions = [];

        foreach ($payload ?? [] as $entry) {
            if (($entry['version_type'] ?? null) === 'release' && !empty($entry['version'])) {
                $versions[] = (string) $entry['version'];
            }
        }

        return $versions;
    }

    /**
     * Searches Modrinth for server usable mods.
     *
     * @param string|null $query        free text, may be null to browse
     * @param string|null $loader       one of self::LOADERS
     * @param string|null $gameVersion  e.g. "1.20.1"
     */
    public function search(?string $query, ?string $loader, ?string $gameVersion, string $sort = 'relevance'): array
    {
        $facets = [['project_type:mod']];

        if ($loader !== null && in_array($loader, self::LOADERS, true)) {
            $facets[] = ['categories:' . $loader];
        }

        if ($gameVersion !== null && $gameVersion !== '') {
            $facets[] = ['versions:' . $gameVersion];
        }

        // Modrinth flags client only projects, which are pointless on a server.
        if (!$this->allowClientMods()) {
            $facets[] = ['server_side:required', 'server_side:optional'];
        }

        $parameters = [
            'limit' => $this->resultLimit(),
            'index' => in_array($sort, self::SORTS, true) ? $sort : 'relevance',
            'facets' => json_encode($facets),
        ];

        if ($query !== null && trim($query) !== '') {
            $parameters['query'] = trim($query);
        }

        $payload = $this->getJson('search:' . md5(json_encode($parameters)), self::API . '/search', $parameters);

        $results = [];

        foreach ($payload['hits'] ?? [] as $hit) {
            if (empty($hit['slug'])) {
                continue;
            }

            $results[] = [
                'slug' => (string) $hit['slug'],
                'title' => (string) ($hit['title'] ?? $hit['slug']),
                'description' => (string) ($hit['description'] ?? ''),
                'author' => (string) ($hit['author'] ?? ''),
                'downloads' => (int) ($hit['downloads'] ?? 0),
                'follows' => (int) ($hit['follows'] ?? 0),
                'icon' => $hit['icon_url'] ?: null,
                'categories' => array_values(array_filter(
                    (array) ($hit['categories'] ?? []),
                    fn ($category) => !in_array($category, self::LOADERS, true)
                )),
                'loaders' => array_values(array_intersect(self::LOADERS, (array) ($hit['categories'] ?? []))),
                'game_versions' => array_values((array) ($hit['versions'] ?? [])),
                'latest_game_version' => $hit['latest_version'] ?? null,
                'client_side' => (string) ($hit['client_side'] ?? 'unknown'),
                'server_side' => (string) ($hit['server_side'] ?? 'unknown'),
                'url' => 'https://modrinth.com/mod/' . $hit['slug'],
            ];
        }

        return $results;
    }

    /**
     * Installable versions of one project, newest first.
     */
    public function versions(string $slug, ?string $loader = null, ?string $gameVersion = null): array
    {
        $parameters = [];

        if ($loader !== null && in_array($loader, self::LOADERS, true)) {
            $parameters['loaders'] = json_encode([$loader]);
        }

        if ($gameVersion !== null && $gameVersion !== '') {
            $parameters['game_versions'] = json_encode([$gameVersion]);
        }

        $payload = $this->getJson(
            'versions:' . $slug . ':' . md5(json_encode($parameters)),
            self::API . '/project/' . rawurlencode($slug) . '/version',
            $parameters
        );

        $versions = [];

        foreach ($payload ?? [] as $entry) {
            $file = $this->primaryFile($entry);

            // A version with no downloadable jar is useless to us.
            if ($file === null) {
                continue;
            }

            $required = 0;

            foreach ((array) ($entry['dependencies'] ?? []) as $dependency) {
                if (($dependency['dependency_type'] ?? null) === 'required') {
                    $required++;
                }
            }

            $versions[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'name' => (string) ($entry['name'] ?? ''),
                'number' => (string) ($entry['version_number'] ?? ''),
                'type' => (string) ($entry['version_type'] ?? 'release'),
                'game_versions' => array_values((array) ($entry['game_versions'] ?? [])),
                'loaders' => array_values((array) ($entry['loaders'] ?? [])),
                'published' => $entry['date_published'] ?? null,
                'downloads' => (int) ($entry['downloads'] ?? 0),
                'filename' => $file['filename'],
                'size' => $file['size'],
                'required_dependencies' => $required,
            ];
        }

        return $versions;
    }

    /**
     * Picks the version to install and returns every jar that has to be pulled.
     *
     * Required dependencies are resolved one level deep, which covers the usual
     * case (a mod needing Fabric API). Dependencies of dependencies are left
     * alone on purpose: silently pulling a whole tree is worse than telling the
     * user what is missing.
     *
     * @return array{version: array, files: array<int, array{slug: string, title: string, version: string, filename: string, url: string, dependency: bool}>}
     */
    public function resolve(string $slug, ?string $versionId, ?string $loader, ?string $gameVersion, bool $withDependencies): array
    {
        $raw = $this->rawVersions($slug, $loader, $gameVersion);

        if (empty($raw)) {
            throw new DisplayException(
                'Modrinth has no build of this mod for the selected loader and game version. Change the filters and try again.'
            );
        }

        $chosen = null;

        if ($versionId !== null && $versionId !== '') {
            foreach ($raw as $entry) {
                if (($entry['id'] ?? null) === $versionId) {
                    $chosen = $entry;

                    break;
                }
            }

            // Never fall back silently: the user asked for a specific build.
            if ($chosen === null) {
                throw new DisplayException('That version is no longer offered for this mod. Reload the list and pick another one.');
            }
        } else {
            $chosen = $raw[0];
        }

        $file = $this->primaryFile($chosen);

        if ($file === null) {
            throw new DisplayException('This version has no downloadable file on Modrinth.');
        }

        $files = [[
            'slug' => $slug,
            'title' => (string) ($chosen['name'] ?? $slug),
            'version' => (string) ($chosen['version_number'] ?? ''),
            'filename' => $file['filename'],
            'url' => $file['url'],
            'dependency' => false,
        ]];

        $missing = [];

        foreach ((array) ($chosen['dependencies'] ?? []) as $dependency) {
            if (($dependency['dependency_type'] ?? null) !== 'required') {
                continue;
            }

            $resolved = $this->resolveDependency($dependency, $loader, $gameVersion);

            if ($resolved === null) {
                $missing[] = $dependency['project_id'] ?? 'unknown';

                continue;
            }

            if ($withDependencies) {
                $files[] = $resolved;
            }
        }

        return [
            'version' => [
                'id' => (string) ($chosen['id'] ?? ''),
                'number' => (string) ($chosen['version_number'] ?? ''),
                'loaders' => array_values((array) ($chosen['loaders'] ?? [])),
                'game_versions' => array_values((array) ($chosen['game_versions'] ?? [])),
            ],
            'files' => $files,
            'unresolved_dependencies' => $missing,
        ];
    }

    /**
     * Turns one required dependency into a downloadable file.
     */
    private function resolveDependency(array $dependency, ?string $loader, ?string $gameVersion): ?array
    {
        $entry = null;

        if (!empty($dependency['version_id'])) {
            $entry = $this->getJson(
                'version:' . $dependency['version_id'],
                self::API . '/version/' . rawurlencode((string) $dependency['version_id'])
            );
        } elseif (!empty($dependency['project_id'])) {
            $candidates = $this->rawVersions((string) $dependency['project_id'], $loader, $gameVersion);
            $entry = $candidates[0] ?? null;
        }

        if (!is_array($entry)) {
            return null;
        }

        $file = $this->primaryFile($entry);

        if ($file === null) {
            return null;
        }

        return [
            'slug' => (string) ($entry['project_id'] ?? ($dependency['project_id'] ?? '')),
            'title' => (string) ($entry['name'] ?? 'Dependency'),
            'version' => (string) ($entry['version_number'] ?? ''),
            'filename' => $file['filename'],
            'url' => $file['url'],
            'dependency' => true,
        ];
    }

    /**
     * Unmodified version payloads, used where dependency data is needed.
     */
    private function rawVersions(string $slug, ?string $loader, ?string $gameVersion): array
    {
        $parameters = [];

        if ($loader !== null && in_array($loader, self::LOADERS, true)) {
            $parameters['loaders'] = json_encode([$loader]);
        }

        if ($gameVersion !== null && $gameVersion !== '') {
            $parameters['game_versions'] = json_encode([$gameVersion]);
        }

        $payload = $this->getJson(
            'raw-versions:' . $slug . ':' . md5(json_encode($parameters)),
            self::API . '/project/' . rawurlencode($slug) . '/version',
            $parameters
        );

        return is_array($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    /**
     * Modrinth marks one file per version as primary; older versions sometimes
     * mark none, in which case the first jar is the right choice.
     */
    private function primaryFile(array $version): ?array
    {
        $files = (array) ($version['files'] ?? []);
        $fallback = null;

        foreach ($files as $file) {
            if (empty($file['url']) || empty($file['filename'])) {
                continue;
            }

            if (!str_ends_with(strtolower((string) $file['filename']), '.jar')) {
                continue;
            }

            $normalised = [
                'filename' => $this->safeFilename((string) $file['filename']),
                'url' => (string) $file['url'],
                'size' => (int) ($file['size'] ?? 0),
            ];

            if ($file['primary'] ?? false) {
                return $normalised;
            }

            $fallback = $fallback ?? $normalised;
        }

        return $fallback;
    }

    /**
     * A filename coming from a third party API ends up in a path handed to the
     * daemon, so anything that could escape /mods is rejected outright.
     */
    public function safeFilename(string $name): string
    {
        $name = trim($name);

        if (
            $name === ''
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, '..')
            || strlen($name) > 180
            || preg_match('/[\x00-\x1F]/', $name) === 1
        ) {
            throw new DisplayException('The mod file name returned by Modrinth is not usable.');
        }

        return $name;
    }

    /**
     * Validates a file name that came from the browser before it is used in a
     * rename or delete call against the daemon.
     */
    public function assertManageableFile(string $name): string
    {
        $name = $this->safeFilename($name);
        $lower = strtolower($name);

        if (!str_ends_with($lower, '.jar') && !str_ends_with($lower, '.jar' . self::DISABLED_SUFFIX)) {
            throw new DisplayException('Only .jar mod files can be managed here.');
        }

        return $name;
    }

    public function flushCache(): void
    {
        Cache::forget('addons:mods:game-versions');
    }

    /**
     * Cached GET against the Modrinth API. Returns null on any failure so the
     * caller can degrade instead of throwing a 500 at the user.
     */
    private function getJson(string $key, string $url, array $parameters = []): ?array
    {
        return Cache::remember('addons:mods:' . $key, self::CACHE_TTL, function () use ($url, $parameters) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                    ->timeout(15)
                    ->get($url, $parameters);

                if (!$response->successful()) {
                    Log::warning(sprintf('Mod Installer: %s returned %d', $url, $response->status()));

                    return null;
                }

                $decoded = $response->json();

                return is_array($decoded) ? $decoded : null;
            } catch (\Throwable $exception) {
                Log::warning(sprintf('Mod Installer: %s failed: %s', $url, $exception->getMessage()));

                return null;
            }
        });
    }
}
