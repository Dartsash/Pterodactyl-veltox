<?php

namespace Pterodactyl\Services\Addons;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Addon;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Resolves the list of downloadable versions for a marketplace addon.
 *
 * The Plugin Manager stores *where* an addon is published (Modrinth project,
 * GitHub repository, Hangar project, or a plain static link) and this service
 * turns that into a concrete list of versions the client can pick from, plus a
 * direct download URL for the version that was chosen.
 *
 * Everything is cached, and every upstream failure degrades gracefully to the
 * single URL stored on the addon row so the marketplace never hard-fails.
 */
class PluginVersionService
{
    public const CACHE_TTL = 1800;

    public const SOURCE_STATIC = 'static';
    public const SOURCE_MODRINTH = 'modrinth';
    public const SOURCE_GITHUB = 'github';
    public const SOURCE_HANGAR = 'hangar';

    public const SOURCES = [
        self::SOURCE_STATIC,
        self::SOURCE_MODRINTH,
        self::SOURCE_GITHUB,
        self::SOURCE_HANGAR,
    ];

    /**
     * How many versions we hand to the UI. Modrinth/GitHub can return hundreds.
     */
    protected const LIMIT = 40;

    /**
     * Loader keywords used to keep plugin builds out of the mod list and vice
     * versa when an upstream project publishes both.
     */
    protected const PLUGIN_LOADERS = ['bukkit', 'spigot', 'paper', 'purpur', 'folia', 'sponge', 'velocity', 'waterfall', 'bungeecord'];
    protected const MOD_LOADERS = ['fabric', 'forge', 'neoforge', 'quilt'];

    /**
     * Every version available for an addon, newest first.
     *
     * @return array<int, array{version: string, url: string, filename: ?string, game_versions: array<int, string>, loaders: array<int, string>, released: ?string, prerelease: bool}>
     */
    public function versions(Addon $addon): array
    {
        $source = $this->sourceFor($addon);

        if ($source === self::SOURCE_STATIC) {
            return $this->staticVersions($addon);
        }

        $versions = Cache::remember(
            $this->cacheKey($addon),
            self::CACHE_TTL,
            fn () => $this->fetch($addon, $source)
        );

        if (empty($versions)) {
            return $this->staticVersions($addon);
        }

        return $versions;
    }

    /**
     * Pick the download for a requested version, falling back to the newest one.
     *
     * @return array{version: string, url: string, filename: ?string}
     */
    public function resolve(Addon $addon, ?string $version = null): array
    {
        $versions = $this->versions($addon);

        if (empty($versions)) {
            throw new DisplayException('No download is available for this addon right now. Please try again later or contact an administrator.');
        }

        if ($version === null || $version === '' || strtolower($version) === 'latest') {
            $chosen = $versions[0];
        } else {
            $chosen = Arr::first($versions, fn (array $entry) => $entry['version'] === $version);
        }

        if ($chosen === null) {
            throw new DisplayException('That version is no longer available upstream. Refresh the page and pick another one.');
        }

        return [
            'version' => $chosen['version'],
            'url' => $chosen['url'],
            'filename' => $chosen['filename'] ?? null,
        ];
    }

    /**
     * True when the addon can offer more than one version.
     */
    public function supportsVersions(Addon $addon): bool
    {
        return $this->sourceFor($addon) !== self::SOURCE_STATIC;
    }

    /**
     * Drop cached version lists (used after an admin edits an addon).
     */
    public function flushCache(?Addon $addon = null): void
    {
        if ($addon !== null) {
            Cache::forget($this->cacheKey($addon));

            return;
        }

        Cache::forever('addons:plugins:generation', $this->generation() + 1);
    }

    protected function sourceFor(Addon $addon): string
    {
        $source = strtolower((string) ($addon->source ?? self::SOURCE_STATIC));

        if (!in_array($source, self::SOURCES, true) || $source === self::SOURCE_STATIC) {
            return self::SOURCE_STATIC;
        }

        return trim((string) $addon->source_id) === '' ? self::SOURCE_STATIC : $source;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function staticVersions(Addon $addon): array
    {
        $url = trim((string) $addon->url);

        if ($url === '') {
            return [];
        }

        return [[
            'version' => (string) ($addon->version ?: 'latest'),
            'url' => $url,
            'filename' => $addon->filename,
            'game_versions' => [],
            'loaders' => [],
            'released' => null,
            'prerelease' => false,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetch(Addon $addon, string $source): array
    {
        try {
            return match ($source) {
                self::SOURCE_MODRINTH => $this->fromModrinth($addon),
                self::SOURCE_GITHUB => $this->fromGithub($addon),
                self::SOURCE_HANGAR => $this->fromHangar($addon),
                default => [],
            };
        } catch (\Throwable $exception) {
            Log::warning(sprintf(
                'Plugin Manager: could not list versions for %s (%s:%s): %s',
                $addon->name,
                $source,
                (string) $addon->source_id,
                $exception->getMessage()
            ));

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fromModrinth(Addon $addon): array
    {
        $project = rawurlencode(trim((string) $addon->source_id));

        $payload = $this->getJson('https://api.modrinth.com/v2/project/' . $project . '/version');

        if (!is_array($payload)) {
            return [];
        }

        $versions = [];

        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $loaders = array_values(array_filter((array) ($entry['loaders'] ?? []), 'is_string'));

            if (!$this->loadersMatchCategory($addon, $loaders)) {
                continue;
            }

            $file = $this->pickModrinthFile((array) ($entry['files'] ?? []), $addon);

            if ($file === null) {
                continue;
            }

            $versions[] = [
                'version' => (string) ($entry['version_number'] ?? $entry['name'] ?? ''),
                'url' => (string) $file['url'],
                'filename' => isset($file['filename']) ? (string) $file['filename'] : null,
                'game_versions' => array_values(array_filter((array) ($entry['game_versions'] ?? []), 'is_string')),
                'loaders' => $loaders,
                'released' => isset($entry['date_published']) ? (string) $entry['date_published'] : null,
                'prerelease' => ($entry['version_type'] ?? 'release') !== 'release',
            ];
        }

        return $this->finalize($versions);
    }

    /**
     * @param array<int, mixed> $files
     *
     * @return array<string, mixed>|null
     */
    protected function pickModrinthFile(array $files, Addon $addon): ?array
    {
        $wanted = $addon->category === 'Datapack' ? '.zip' : '.jar';

        $candidates = [];

        foreach ($files as $file) {
            if (!is_array($file) || !isset($file['url'])) {
                continue;
            }

            $name = strtolower((string) ($file['filename'] ?? ''));

            // Sources, javadoc and dev jars are never what a server wants.
            if (Str::contains($name, ['-sources', '-javadoc', '-dev.', '-slim'])) {
                continue;
            }

            if ($wanted === '.zip' && !Str::endsWith($name, ['.zip', '.jar'])) {
                continue;
            }

            if ($wanted === '.jar' && !Str::endsWith($name, '.jar')) {
                continue;
            }

            $candidates[] = $file;
        }

        if (empty($candidates)) {
            return null;
        }

        // Modrinth marks the recommended file as primary.
        return Arr::first($candidates, fn (array $file) => (bool) ($file['primary'] ?? false)) ?? $candidates[0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fromGithub(Addon $addon): array
    {
        $repo = trim((string) $addon->source_id, " \t\n\r/");

        $payload = $this->getJson('https://api.github.com/repos/' . $repo . '/releases?per_page=30');

        if (!is_array($payload)) {
            return [];
        }

        $versions = [];

        foreach ($payload as $release) {
            if (!is_array($release) || ($release['draft'] ?? false)) {
                continue;
            }

            $asset = $this->pickGithubAsset((array) ($release['assets'] ?? []), $addon);

            if ($asset === null) {
                continue;
            }

            $tag = (string) ($release['tag_name'] ?? $release['name'] ?? '');

            $versions[] = [
                'version' => ltrim($tag, 'vV') !== '' ? ltrim($tag, 'vV') : $tag,
                'url' => (string) $asset['browser_download_url'],
                'filename' => isset($asset['name']) ? (string) $asset['name'] : null,
                'game_versions' => [],
                'loaders' => [],
                'released' => isset($release['published_at']) ? (string) $release['published_at'] : null,
                'prerelease' => (bool) ($release['prerelease'] ?? false),
            ];
        }

        return $this->finalize($versions);
    }

    /**
     * Picks the asset that best matches the addon. The saved filename is used as
     * a hint, which is how EssentialsX modules (EssentialsX, EssentialsXChat,
     * EssentialsXSpawn) all resolve from a single repository.
     *
     * @param array<int, mixed> $assets
     *
     * @return array<string, mixed>|null
     */
    protected function pickGithubAsset(array $assets, Addon $addon): ?array
    {
        $extension = $addon->category === 'Datapack' ? '.zip' : '.jar';
        $hint = strtolower(pathinfo((string) $addon->filename, PATHINFO_FILENAME));

        $candidates = [];

        foreach ($assets as $asset) {
            if (!is_array($asset) || !isset($asset['browser_download_url'])) {
                continue;
            }

            $name = strtolower((string) ($asset['name'] ?? ''));

            if (!Str::endsWith($name, $extension)) {
                continue;
            }

            if (Str::contains($name, ['-sources', '-javadoc', '-dev.', 'checksum', '.sha256', '.asc'])) {
                continue;
            }

            $candidates[] = $asset;
        }

        if (empty($candidates)) {
            return null;
        }

        if ($hint !== '') {
            $exact = Arr::first(
                $candidates,
                fn (array $asset) => Str::startsWith(strtolower((string) $asset['name']), $hint . '-')
                    || strtolower((string) $asset['name']) === $hint . $extension
            );

            if ($exact !== null) {
                return $exact;
            }

            $loose = Arr::first(
                $candidates,
                fn (array $asset) => Str::contains(strtolower((string) $asset['name']), $hint)
            );

            if ($loose !== null) {
                return $loose;
            }
        }

        // Shortest name wins: "Plan-5.6.jar" beats "Plan-5.6-all-platforms.jar".
        usort($candidates, fn (array $a, array $b) => strlen((string) $a['name']) <=> strlen((string) $b['name']));

        return $candidates[0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fromHangar(Addon $addon): array
    {
        $project = trim((string) $addon->source_id, " \t\n\r/");
        $project = Str::afterLast($project, '/');

        $payload = $this->getJson('https://hangar.papermc.io/api/v1/projects/' . rawurlencode($project) . '/versions?limit=25&offset=0');

        if (!is_array($payload) || !is_array($payload['result'] ?? null)) {
            return [];
        }

        $versions = [];

        foreach ($payload['result'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = (string) ($entry['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $platform = null;

            foreach (['PAPER', 'WATERFALL', 'VELOCITY'] as $candidate) {
                if (isset($entry['downloads'][$candidate])) {
                    $platform = $candidate;

                    break;
                }
            }

            if ($platform === null) {
                continue;
            }

            $download = (array) $entry['downloads'][$platform];
            $url = (string) ($download['downloadUrl'] ?? $download['externalUrl'] ?? '');

            if ($url === '') {
                $url = sprintf(
                    'https://hangar.papermc.io/api/v1/projects/%s/versions/%s/%s/download',
                    rawurlencode($project),
                    rawurlencode($name),
                    $platform
                );
            }

            $versions[] = [
                'version' => $name,
                'url' => $url,
                'filename' => isset($download['fileInfo']['name']) ? (string) $download['fileInfo']['name'] : null,
                'game_versions' => array_values(array_filter((array) ($entry['platformDependencies'][$platform] ?? []), 'is_string')),
                'loaders' => [strtolower($platform)],
                'released' => isset($entry['createdAt']) ? (string) $entry['createdAt'] : null,
                'prerelease' => ($entry['channel']['name'] ?? 'Release') !== 'Release',
            ];
        }

        return $this->finalize($versions);
    }

    /**
     * @param array<int, string> $loaders
     */
    protected function loadersMatchCategory(Addon $addon, array $loaders): bool
    {
        if (empty($loaders)) {
            return true;
        }

        $loaders = array_map('strtolower', $loaders);

        // Datapacks are published under the "datapack" loader, but plenty of
        // packs are also shipped as mods, so we stay permissive there.
        if ($addon->category === 'Datapack') {
            return true;
        }

        $wanted = $addon->category === 'Mod' ? self::MOD_LOADERS : self::PLUGIN_LOADERS;

        return count(array_intersect($loaders, $wanted)) > 0;
    }

    /**
     * Drop broken entries, de-duplicate and trim the list.
     *
     * @param array<int, array<string, mixed>> $versions
     *
     * @return array<int, array<string, mixed>>
     */
    protected function finalize(array $versions): array
    {
        $seen = [];
        $result = [];

        foreach ($versions as $version) {
            $number = trim((string) $version['version']);
            $url = trim((string) $version['url']);

            if ($number === '' || $url === '' || isset($seen[$number])) {
                continue;
            }

            $seen[$number] = true;
            $version['version'] = $number;
            $version['url'] = $url;
            $result[] = $version;

            if (count($result) >= self::LIMIT) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return array<mixed>|null
     */
    protected function getJson(string $url): ?array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'Pterodactyl-Panel/PluginManager (+' . config('app.url') . ')',
        ])->timeout(10)->connectTimeout(5)->get($url);

        if (!$response->successful()) {
            Log::warning(sprintf('Plugin Manager: %s returned %d', $url, $response->status()));

            return null;
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : null;
    }

    protected function cacheKey(Addon $addon): string
    {
        return sprintf(
            'addons:plugins:g%d:%s:%s',
            $this->generation(),
            $this->sourceFor($addon),
            md5(strtolower((string) $addon->source_id) . '|' . $addon->category . '|' . $addon->filename)
        );
    }

    protected function generation(): int
    {
        return (int) Cache::get('addons:plugins:generation', 0);
    }
}
