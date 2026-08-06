<?php

namespace Pterodactyl\Services\Addons;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Backs the "Version Manager" addon.
 *
 * Talks to the official distribution APIs of every supported server core so a
 * user can pick a core plus a version (and a build where the project publishes
 * them) and have the matching jar downloaded straight into their server.
 *
 * Each upstream lookup accepts a list of endpoints and falls through to the
 * next one when a host is unreachable or answers with an error, which keeps the
 * addon working when a project retires an API version.
 */
class VersionManagerService
{
    public const SETTING_ENABLED = 'settings::addons:versions_enabled';
    public const SETTING_CORES = 'settings::addons:versions_cores';

    /** How long version and build listings are cached, in seconds. */
    public const CACHE_TTL = 1800;

    /**
     * Every core the addon knows how to install.
     *
     * type selects the upstream integration:
     *  - papermc:  PaperMC style API (v2 with a v3 fallback)
     *  - purpur:   PurpurMC v2 API
     *  - vanilla:  Mojang version manifest (releases or snapshots)
     *  - fabric:   FabricMC meta API
     *  - quilt:    QuiltMC meta API, ships an installer
     *  - forge:    Forge maven metadata, ships an installer
     *  - neoforge: NeoForged maven, ships an installer
     *  - mohist:   MohistMC API
     *  - leaves:   LeavesMC GitHub releases
     *  - bukkit:   getbukkit downloads, version list mirrored from Paper
     *  - static:   a single always-latest artifact
     */
    public const CORES = [
        // Minecraft servers
        'paper' => [
            'name' => 'Paper',
            'type' => 'papermc',
            'project' => 'paper',
            'host' => 'https://api.papermc.io',
            'category' => 'server',
            'description' => 'High performance Spigot fork. The default choice for most servers.',
        ],
        'purpur' => [
            'name' => 'Purpur',
            'type' => 'purpur',
            'project' => 'purpur',
            'category' => 'server',
            'description' => 'Paper fork with a huge amount of extra gameplay configuration.',
        ],
        'folia' => [
            'name' => 'Folia',
            'type' => 'papermc',
            'project' => 'folia',
            'host' => 'https://api.papermc.io',
            'category' => 'server',
            'description' => 'Paper fork with regionised multithreading. Experimental.',
        ],
        'leaves' => [
            'name' => 'Leaves',
            'type' => 'leaves',
            'project' => 'leaves',
            'category' => 'server',
            'description' => 'Paper fork that restores vanilla behaviour and mechanics.',
        ],
        'spigot' => [
            'name' => 'Spigot',
            'type' => 'bukkit',
            'project' => 'spigot',
            'category' => 'server',
            'description' => 'The classic plugin server, downloaded from getbukkit.org.',
        ],
        'craftbukkit' => [
            'name' => 'CraftBukkit',
            'type' => 'bukkit',
            'project' => 'craftbukkit',
            'category' => 'server',
            'description' => 'The original Bukkit server. Legacy, use Paper instead when possible.',
        ],
        'vanilla' => [
            'name' => 'Vanilla',
            'type' => 'vanilla',
            'project' => 'release',
            'category' => 'server',
            'description' => 'The official Mojang server release, no plugin support.',
        ],
        'snapshot' => [
            'name' => 'Vanilla Snapshot',
            'type' => 'vanilla',
            'project' => 'snapshot',
            'category' => 'server',
            'description' => 'Mojang development snapshots, including pre-releases.',
        ],

        // Mod loaders
        'fabric' => [
            'name' => 'Fabric',
            'type' => 'fabric',
            'project' => 'fabric',
            'category' => 'modded',
            'description' => 'Lightweight mod loader. The build list holds the loader versions.',
        ],
        'quilt' => [
            'name' => 'Quilt',
            'type' => 'quilt',
            'project' => 'quilt',
            'category' => 'modded',
            'description' => 'Community fork of Fabric. Downloads the universal installer.',
        ],
        'forge' => [
            'name' => 'Forge',
            'type' => 'forge',
            'project' => 'forge',
            'category' => 'modded',
            'description' => 'Classic mod loader. Downloads the installer, which runs once.',
        ],
        'neoforge' => [
            'name' => 'NeoForge',
            'type' => 'neoforge',
            'project' => 'neoforge',
            'category' => 'modded',
            'description' => 'Modern Forge fork for 1.20.2 and newer. Downloads the installer.',
        ],
        'mohist' => [
            'name' => 'Mohist',
            'type' => 'mohist',
            'project' => 'mohist',
            'category' => 'modded',
            'description' => 'Hybrid server running Forge mods and Bukkit plugins together.',
        ],

        // Proxies
        'velocity' => [
            'name' => 'Velocity',
            'type' => 'papermc',
            'project' => 'velocity',
            'host' => 'https://api.papermc.io',
            'category' => 'proxy',
            'description' => 'Modern, high performance proxy from the PaperMC team.',
        ],
        'waterfall' => [
            'name' => 'Waterfall',
            'type' => 'papermc',
            'project' => 'waterfall',
            'host' => 'https://api.papermc.io',
            'category' => 'proxy',
            'description' => 'BungeeCord fork by PaperMC. Legacy, superseded by Velocity.',
        ],
        'bungeecord' => [
            'name' => 'BungeeCord',
            'type' => 'static',
            'project' => 'bungeecord',
            'category' => 'proxy',
            'description' => 'The original proxy from md-5. Only the latest build is published.',
            'url' => 'https://ci.md-5.net/job/BungeeCord/lastSuccessfulBuild/artifact/bootstrap/target/BungeeCord.jar',
        ],
    ];

    public const CATEGORIES = [
        'server' => 'Minecraft servers',
        'modded' => 'Mod loaders',
        'proxy' => 'Proxies',
    ];

    /** Cores that download an installer instead of a ready to run jar. */
    public const INSTALLERS = ['forge', 'neoforge', 'quilt'];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * All cached listings live behind a generation counter, so the refresh
     * button in the admin area can drop every entry at once (including the
     * per version build lists, whose keys are not enumerable).
     */
    protected function cacheGeneration(): int
    {
        return (int) (Cache::get('addons:versions:generation') ?: 1);
    }

    protected function cacheKey(string $suffix): string
    {
        return 'addons:versions:g' . $this->cacheGeneration() . ':' . $suffix;
    }

    public function addonEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING_ENABLED, '1');
    }

    /**
     * Keys of the cores an administrator made available.
     */
    public function enabledCoreKeys(): array
    {
        $stored = $this->settings->get(self::SETTING_CORES);

        if (empty($stored)) {
            return array_keys(self::CORES);
        }

        $decoded = json_decode($stored, true);

        if (!is_array($decoded)) {
            return array_keys(self::CORES);
        }

        return array_values(array_intersect(array_keys(self::CORES), $decoded));
    }

    public function saveEnabledCores(array $keys): void
    {
        $keys = array_values(array_intersect(array_keys(self::CORES), $keys));

        $this->settings->set(self::SETTING_CORES, json_encode($keys));
    }

    /**
     * The cores available to a user, shaped for the client API.
     */
    public function cores(): array
    {
        $cores = [];

        foreach ($this->enabledCoreKeys() as $key) {
            $core = self::CORES[$key];

            $cores[] = [
                'key' => $key,
                'name' => $core['name'],
                'category' => $core['category'],
                'category_label' => self::CATEGORIES[$core['category']] ?? $core['category'],
                'description' => $core['description'],
                'installer' => in_array($key, self::INSTALLERS, true),
                'has_builds' => in_array($core['type'], ['papermc', 'purpur', 'fabric', 'quilt', 'forge', 'neoforge', 'mohist'], true),
            ];
        }

        return $cores;
    }

    public function assertCoreAvailable(string $core): array
    {
        if (!array_key_exists($core, self::CORES) || !in_array($core, $this->enabledCoreKeys(), true)) {
            throw new BadRequestHttpException('That server core is not available on this panel.');
        }

        return self::CORES[$core];
    }

    /**
     * Every installable version for a core, newest first.
     */
    public function versions(string $core): array
    {
        $definition = $this->assertCoreAvailable($core);

        $versions = Cache::remember($this->cacheKey($core), self::CACHE_TTL, function () use ($core, $definition) {
            // Upstream projects change their JSON shape without warning, so a
            // surprise here must never reach the user as a bare 500.
            try {
                return $this->flatten($this->fetchVersions($core, $definition));
            } catch (BadRequestHttpException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                Log::error(sprintf(
                    'Version Manager: reading %s versions failed: %s in %s:%d',
                    $core,
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                ));

                throw new BadRequestHttpException(sprintf(
                    '%s changed the format of its version list, so it cannot be read right now. The details are in the panel log.',
                    $definition['name']
                ));
            }
        });

        if (empty($versions)) {
            Cache::forget($this->cacheKey($core));

            throw new BadRequestHttpException($definition['name'] . ' did not return any versions right now. Try again in a moment.');
        }

        return $versions;
    }

    /**
     * Reduce whatever an upstream API returned to a plain list of unique,
     * non-empty version strings. Nested arrays are walked, because several
     * projects group their releases, and anything that is not scalar is
     * dropped instead of being turned into the literal string "Array".
     *
     * @param array<int|string, mixed> $versions
     */
    protected function flatten(array $versions): array
    {
        $flat = [];

        array_walk_recursive($versions, function ($value) use (&$flat) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $value = trim((string) $value);

                if ($value !== '') {
                    $flat[] = $value;
                }
            }
        });

        return array_values(array_unique($flat));
    }

    protected function fetchVersions(string $core, array $definition): array
    {
        switch ($definition['type']) {
            case 'papermc':
                return $this->paperVersions($definition);

            case 'leaves':
                return array_keys($this->leavesMap());

            case 'purpur':
                $data = $this->get($definition['name'], ['https://api.purpurmc.org/v2/purpur']);

                return array_values(array_reverse(Arr::get($data, 'versions', [])));

            case 'vanilla':
                $manifest = $this->get($definition['name'], [
                    'https://launchermeta.mojang.com/mc/game/version_manifest_v2.json',
                    'https://piston-meta.mojang.com/mc/game/version_manifest_v2.json',
                ]);

                $wanted = $definition['project'] === 'snapshot' ? ['snapshot'] : ['release'];

                return collect(Arr::get($manifest, 'versions', []))
                    ->filter(fn ($entry) => in_array(Arr::get($entry, 'type'), $wanted, true))
                    ->pluck('id')
                    ->values()
                    ->all();

            case 'fabric':
                $data = $this->get($definition['name'], ['https://meta.fabricmc.net/v2/versions/game']);

                return collect($data)->where('stable', true)->pluck('version')->values()->all();

            case 'quilt':
                $data = $this->get($definition['name'], ['https://meta.quiltmc.org/v3/versions/game']);

                return collect($data)->where('stable', true)->pluck('version')->values()->all();

            case 'forge':
                $data = $this->get($definition['name'], [
                    'https://files.minecraftforge.net/net/minecraftforge/forge/maven-metadata.json',
                    'https://bmclapi2.bangbang93.com/forge/minecraft',
                ]);

                // The official endpoint returns a map keyed by Minecraft
                // version. The mirror returns a list, sometimes of plain
                // strings and sometimes of objects with an mcversion field.
                if (Arr::isList($data)) {
                    return array_reverse(array_map(function ($entry) {
                        if (is_array($entry)) {
                            return Arr::get($entry, 'mcversion') ?? Arr::get($entry, 'version') ?? Arr::get($entry, 'name');
                        }

                        return $entry;
                    }, $data));
                }

                return array_values(array_reverse(array_keys($data)));

            case 'neoforge':
                return array_keys($this->neoforgeMap());

            case 'mohist':
                // Maven Central is the only Mohist mirror that still serves
                // jars reliably (mohistmc.com retired its api/v2 endpoints and
                // now answers with the website itself). The MohistMC API is
                // only used as a fallback when the mirror has nothing.
                $versions = array_keys($this->mohistMap());

                if (!empty($versions)) {
                    return $versions;
                }

                $data = $this->get($definition['name'], [
                    'https://api.mohistmc.com/api/v2/projects/mohist',
                    'https://api.mohistmc.com/project/mohist/versions',
                ]);

                $apiVersions = Arr::get($data, 'versions', Arr::isList($data) ? $data : []);

                // Newer Mohist responses list objects rather than strings.
                return array_reverse(array_map(function ($entry) {
                    if (is_array($entry)) {
                        return Arr::get($entry, 'version') ?? Arr::get($entry, 'name') ?? Arr::get($entry, 'id');
                    }

                    return $entry;
                }, (array) $apiVersions));

            case 'bukkit':
                // Neither project publishes an index, so the Paper listing is
                // used as the source of truth for which releases exist.
                return $this->paperVersions([
                    'name' => $definition['name'],
                    'project' => 'paper',
                    'host' => 'https://api.papermc.io',
                ]);

            case 'static':
            default:
                return ['latest'];
        }
    }

    /**
     * Builds published for a version, newest first. Empty when the project does
     * not use build numbers.
     */
    public function builds(string $core, string $version): array
    {
        // PHP turns numeric array keys into integers, so cast them back to
        // strings before they are handed to the API.
        return array_map('strval', array_keys($this->buildMap($core, $version)));
    }

    /**
     * Build identifier => direct download URL (null when the URL follows a
     * predictable pattern and is built at install time).
     */
    protected function buildMap(string $core, string $version): array
    {
        $definition = $this->assertCoreAvailable($core);
        $this->assertVersion($core, $version);

        return Cache::remember(
            $this->cacheKey($core . ':' . md5($version)),
            self::CACHE_TTL,
            function () use ($core, $definition, $version) {
                try {
                    return $this->fetchBuilds($core, $definition, $version);
                } catch (BadRequestHttpException $exception) {
                    throw $exception;
                } catch (\Throwable $exception) {
                    Log::error(sprintf(
                        'Version Manager: reading %s builds for %s failed: %s in %s:%d',
                        $core,
                        $version,
                        $exception->getMessage(),
                        $exception->getFile(),
                        $exception->getLine()
                    ));

                    throw new BadRequestHttpException(sprintf(
                        '%s changed the format of its build list for %s, so it cannot be read right now. The details are in the panel log.',
                        $definition['name'],
                        $version
                    ));
                }
            }
        );
    }

    /**
     * Build identifier => download URL for one core and version.
     */
    protected function fetchBuilds(string $core, array $definition, string $version): array
    {
        switch ($definition['type']) {
            case 'papermc':
                return $this->paperBuilds($definition, $version);

            case 'leaves':
                $map = [];

                foreach ($this->leavesMap()[$version] ?? [] as $build => $urls) {
                    $map[(string) $build] = $urls;
                }

                return $map;

            case 'purpur':
                $data = $this->get($definition['name'], ['https://api.purpurmc.org/v2/purpur/' . rawurlencode($version)]);
                $builds = array_reverse(Arr::get($data, 'builds.all', []));

                return $this->mapBuilds($builds, fn ($build) => sprintf(
                    'https://api.purpurmc.org/v2/purpur/%s/%s/download',
                    rawurlencode($version),
                    rawurlencode($build)
                ));

            case 'fabric':
                $data = $this->get($definition['name'], ['https://meta.fabricmc.net/v2/versions/loader/' . rawurlencode($version)]);
                $installer = $this->fabricInstallerVersion();
                $loaders = collect($data)->pluck('loader.version')->filter()->values()->all();

                return $this->mapBuilds($loaders, fn ($loader) => sprintf(
                    'https://meta.fabricmc.net/v2/versions/loader/%s/%s/%s/server/jar',
                    rawurlencode($version),
                    rawurlencode($loader),
                    rawurlencode($installer)
                ));

            case 'quilt':
                $data = $this->get($definition['name'], ['https://meta.quiltmc.org/v3/versions/loader/' . rawurlencode($version)]);
                $loaders = collect($data)->pluck('loader.version')->filter()->values()->all();

                // Quilt only ships an installer, identical for every loader.
                return $this->mapBuilds($loaders, fn () => 'https://quiltmc.org/api/v1/download-latest-installer/java-universal');

            case 'forge':
                $data = $this->get($definition['name'], [
                    'https://files.minecraftforge.net/net/minecraftforge/forge/maven-metadata.json',
                ]);
                $builds = array_reverse(Arr::get($data, $version, []));

                return $this->mapBuilds($builds, fn ($build) => sprintf(
                    'https://maven.minecraftforge.net/net/minecraftforge/forge/%1$s/forge-%1$s-installer.jar',
                    rawurlencode($build)
                ));

            case 'neoforge':
                $builds = $this->neoforgeMap()[$version] ?? [];

                return $this->mapBuilds($builds, fn ($build) => sprintf(
                    'https://maven.neoforged.net/releases/net/neoforged/neoforge/%1$s/neoforge-%1$s-installer.jar',
                    rawurlencode($build)
                ));

            case 'mohist':
                // Builds come from the Maven Central mirror, which keeps every
                // published build. The old API is only consulted when the
                // mirror knows nothing about the version.
                $map = [];

                foreach ($this->mohistMap()[$version] ?? [] as $artifact) {
                    $build = trim(substr($artifact, strlen($version)), '-');

                    if ($build === '') {
                        continue;
                    }

                    $map[$build] = array_values(array_unique(array_filter(array_merge(
                        $this->mohistMavenDownloads($artifact),
                        $this->mohistApiDownloads($version, $build)
                    ))));
                }

                if (!empty($map)) {
                    return $map;
                }

                return $this->mohistBuilds($definition, $version);

            default:
                return [];
        }
    }

    /**
     * Work out the direct download for a core/version/build combination.
     *
     * @return array{url: string, filename: string, installer: bool, label: string}
     */
    public function resolveDownload(string $core, string $version, ?string $build, string $defaultJar): array
    {
        $definition = $this->assertCoreAvailable($core);
        $this->assertVersion($core, $version);

        $map = $this->buildMap($core, $version);
        $isInstaller = in_array($core, self::INSTALLERS, true);

        if (!empty($map)) {
            $build = $build === null || $build === '' ? (string) array_key_first($map) : (string) $build;

            if (!array_key_exists($build, $map)) {
                throw new BadRequestHttpException('That build does not exist for the selected version.');
            }

            $url = $map[$build];
        } else {
            $build = null;
            $url = $this->directUrl($core, $definition, $version);
        }

        // A build may carry several candidate URLs; the first one that actually
        // serves a jar wins.
        $candidates = array_values(array_filter(is_array($url) ? $url : [$url]));

        if (empty($candidates)) {
            throw new BadRequestHttpException('No download could be resolved for that selection.');
        }

        $url = $this->pickDownload($definition['name'], $candidates);

        // Wings does not follow HTTP redirects, so the final location has
        // to be resolved here. GitHub release assets always redirect.
        $url = $this->followRedirects($url);

        $label = $definition['name'] . ' ' . ($version === 'latest' ? 'latest' : $version)
            . ($build ? ' (build ' . $build . ')' : '');

        return [
            'url' => $url,
            'filename' => $isInstaller ? $core . '-installer.jar' : $defaultJar,
            'installer' => $isInstaller,
            'label' => $label,
        ];
    }

    /**
     * Download URL (or list of candidate URLs) for cores that do not publish
     * builds.
     *
     * @return string|array<int, string>|null
     */
    protected function directUrl(string $core, array $definition, string $version): string|array|null
    {
        switch ($definition['type']) {
            case 'vanilla':
                return $this->vanillaDownload($definition['name'], $version);

            case 'leaves':
                // Only reached when no build list could be read at all.
                $builds = $this->leavesMap()[$version] ?? [];

                return empty($builds) ? null : array_values($builds)[0];

            case 'mohist':
                // Last resort when no build list could be read at all.
                return $this->mohistApiDownloads($version, 'latest', true);

            case 'bukkit':
                return sprintf(
                    'https://download.getbukkit.org/%1$s/%1$s-%2$s.jar',
                    $definition['project'],
                    rawurlencode($version)
                );

            case 'static':
            default:
                return $definition['url'] ?? null;
        }
    }

    /**
     * Version list for a PaperMC style API, trying the v2 API first and the
     * newer v3 "fill" API as a fallback.
     */
    protected function paperVersions(array $definition): array
    {
        $host = $definition['host'] ?? 'https://api.papermc.io';
        $project = $definition['project'];

        // PaperMC retired the v2 API (it answers HTTP 410 Gone), so v3 is tried
        // first and v2 only remains for third party hosts that still run it.
        try {
            $data = $this->get($definition['name'], array_values(array_unique([
                'https://fill.papermc.io/v3/projects/' . $project,
                $host . '/v3/projects/' . $project,
            ])));

            $versions = $this->extractVersionStrings($data);

            if (!empty($versions)) {
                return $versions;
            }
        } catch (BadRequestHttpException $exception) {
            // Fall through to the legacy v2 API below.
        }

        $data = $this->get($definition['name'], [$host . '/v2/projects/' . $project]);

        return $this->flatten((array) Arr::get($data, 'versions', []));
    }

    /**
     * Builds for a PaperMC style API. Returns build id => download URL.
     */
    protected function paperBuilds(array $definition, string $version): array
    {
        $host = $definition['host'] ?? 'https://api.papermc.io';
        $project = $definition['project'];

        // v3 first, because PaperMC's v2 API is gone (HTTP 410).
        try {
            $map = $this->paperV3Builds($definition, $host, $project, $version);

            if (!empty($map)) {
                return $map;
            }
        } catch (BadRequestHttpException $exception) {
            // Fall through to the legacy v2 API below.
        }

        $data = $this->get($definition['name'], [
            sprintf('%s/v2/projects/%s/versions/%s', $host, $project, rawurlencode($version)),
        ], 'builds');

        $builds = array_reverse($this->flatten((array) Arr::get($data, 'builds', [])));

        return $this->mapBuilds($builds, fn ($build) => sprintf(
            '%1$s/v2/projects/%2$s/versions/%3$s/builds/%4$s/downloads/%2$s-%3$s-%4$s.jar',
            $host,
            $project,
            rawurlencode($version),
            rawurlencode($build)
        ));
    }

    /**
     * Builds for one version from the PaperMC v3 ("fill") API.
     */
    protected function paperV3Builds(array $definition, string $host, string $project, string $version): array
    {
        $data = $this->get($definition['name'], array_values(array_unique([
            sprintf('https://fill.papermc.io/v3/projects/%s/versions/%s/builds', $project, rawurlencode($version)),
            sprintf('%s/v3/projects/%s/versions/%s/builds', $host, $project, rawurlencode($version)),
        ])), 'builds');

        // Some deployments wrap the list in a "builds" key.
        $data = Arr::get($data, 'builds', $data);

        $map = [];

        foreach ((array) $data as $build) {
            if (!is_array($build)) {
                $id = trim((string) $build);

                if ($id !== '') {
                    $map[$id] = null;
                }

                continue;
            }

            $id = (string) (Arr::get($build, 'id') ?? Arr::get($build, 'build') ?? '');

            if ($id === '') {
                continue;
            }

            $url = Arr::get($build, 'downloads.server:default.url')
                ?? Arr::get($build, 'downloads.application.url')
                ?? Arr::get($build, 'downloads.server.url')
                ?? Arr::get($build, 'downloads.0.url');

            $map[$id] = $this->jarUrl($url);
        }

        return $map;
    }

    /**
     * Minecraft version => build number => candidate download URLs, newest
     * build first, read from the LeavesMC GitHub releases.
     *
     * LeavesMC does not run a PaperMC compatible API: api.leavesmc.org serves
     * its own schema and is not always reachable from every network. Every
     * Leaves build is published as a GitHub release tagged
     * "<minecraft>-<build>-<commit>" with a "leaves-<minecraft>.jar" asset, so
     * the releases are used as the source of truth instead.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    protected function leavesMap(): array
    {
        return Cache::remember($this->cacheKey('leaves:map'), self::CACHE_TTL, function () {
            $map = [];

            // Three pages cover every Minecraft version Leaves still builds.
            foreach ([1, 2, 3] as $page) {
                try {
                    $releases = $this->get('Leaves', [
                        'https://api.github.com/repos/LeavesMC/Leaves/releases?per_page=100&page=' . $page,
                    ]);
                } catch (BadRequestHttpException $exception) {
                    // Keep whatever earlier pages returned.
                    break;
                }

                if (empty($releases)) {
                    break;
                }

                foreach ($releases as $release) {
                    $tag = (string) (Arr::get($release, 'tag_name') ?? '');

                    // Tags look like "1.21.10-147-544ee78".
                    if (!preg_match('#^(1\.[0-9]+(?:\.[0-9]+)?)-([0-9]+)#', $tag, $parts)) {
                        continue;
                    }

                    $version = $parts[1];
                    $build = $parts[2];

                    if (isset($map[$version][$build])) {
                        continue;
                    }

                    $urls = [];

                    foreach ((array) Arr::get($release, 'assets', []) as $asset) {
                        $name = strtolower((string) Arr::get($asset, 'name'));
                        $url = (string) Arr::get($asset, 'browser_download_url');

                        if ($url === '' || !str_ends_with($name, '.jar')) {
                            continue;
                        }

                        // Skip anything that is not the runnable server jar.
                        if (str_contains($name, 'sources') || str_contains($name, 'javadoc')) {
                            continue;
                        }

                        $urls[] = $url;
                    }

                    // Fallback for releases whose asset list came back empty.
                    $urls[] = sprintf(
                        'https://github.com/LeavesMC/Leaves/releases/download/%s/leaves-%s.jar',
                        rawurlencode($tag),
                        rawurlencode($version)
                    );

                    $map[$version][$build] = array_values(array_unique($urls));
                }

                if (count($releases) < 100) {
                    break;
                }
            }

            // Newest build first inside a version, newest version first overall.
            foreach ($map as $version => $builds) {
                krsort($builds, SORT_NUMERIC);
                $map[$version] = $builds;
            }

            uksort($map, fn ($a, $b) => version_compare($this->numeric($b), $this->numeric($a)));

            return $map;
        });
    }

    /**
     * Follow HTTP redirects and return the final URL.
     *
     * Wings hands the URL straight to its own downloader, which does not
     * follow redirects, so a 302 (every GitHub release asset) would fail with
     * "got bad response status from endpoint". The original URL is returned
     * unchanged when nothing redirects or the check cannot be completed.
     */
    protected function followRedirects(string $url): string
    {
        $current = $url;

        for ($hop = 0; $hop < 5; $hop++) {
            try {
                $response = Http::timeout(15)
                    ->connectTimeout(8)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders([
                        'User-Agent' => 'Pterodactyl-VersionManager/1.0',
                        // Only the headers matter here, not the payload.
                        'Range' => 'bytes=0-0',
                    ])
                    ->get($current);
            } catch (\Throwable $exception) {
                return $current;
            }

            if (!in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return $current;
            }

            $location = $response->header('Location');

            if (!is_string($location) || $location === '') {
                return $current;
            }

            // Relative redirects are resolved against the current URL.
            if (!str_starts_with(strtolower($location), 'http')) {
                $parts = parse_url($current);
                $location = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
                    . (str_starts_with($location, '/') ? '' : '/') . $location;
            }

            // Never downgrade to plain HTTP on the way.
            if (!str_starts_with(strtolower($location), 'https://')) {
                return $current;
            }

            $current = $location;
        }

        return $current;
    }

    /**
     * Pick the first candidate URL that really serves a jar.
     *
     * Wings downloads in the background, so a URL that answers with an HTML
     * error page silently leaves a broken server.jar behind and the user never
     * sees a warning. Checking here turns that into a clear error instead.
     *
     * @param array<int, string> $candidates
     */
    protected function pickDownload(string $label, array $candidates): string
    {
        $unverified = null;
        $rejected = null;

        foreach ($candidates as $candidate) {
            $state = $this->probeDownload($candidate);

            if ($state === true) {
                return $candidate;
            }

            // The check itself failed (timeout, blocked range request, ...), so
            // the URL is kept as a last resort rather than rejected.
            if ($state === null && $unverified === null) {
                $unverified = $candidate;
            }

            if ($state === false) {
                $rejected = $candidate;
            }
        }

        if ($unverified !== null) {
            return $unverified;
        }

        Log::warning(sprintf('Version Manager: no working download for %s, last tried %s', $label, (string) $rejected));

        throw new BadRequestHttpException(sprintf(
            '%s does not currently serve a jar for that build. Pick another build or version.',
            $label
        ));
    }

    /**
     * true when the URL serves a jar, false when it clearly does not, and null
     * when the check could not be completed.
     */
    protected function probeDownload(string $url): ?bool
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Range' => 'bytes=0-1023'])
                ->get($url);
        } catch (\Throwable $exception) {
            return null;
        }

        // Some hosts reject range requests outright; that says nothing about
        // the file itself.
        if (in_array($response->status(), [405, 416, 429], true)) {
            return null;
        }

        if (!in_array($response->status(), [200, 206], true)) {
            return false;
        }

        $type = strtolower((string) $response->header('Content-Type'));

        if (str_contains($type, 'html') || str_contains($type, 'json')) {
            return false;
        }

        $head = substr((string) $response->body(), 0, 2);

        if ($head === '') {
            return null;
        }

        // Every jar is a zip archive, so it must start with the PK signature.
        return $head === 'PK';
    }

    /**
     * Minecraft version => Mohist artifact versions (newest build first), read
     * from the Maven Central mirror.
     *
     * mohistmc.com used to expose /api/v2/projects/mohist/..., but the current
     * website no longer serves those paths, so every download built from them
     * ends up being an HTML page (or a 404). Maven Central still hosts the
     * original "-server.jar" artifacts, which is what a Pterodactyl server
     * needs, so it is used as the primary source.
     *
     * @return array<string, array<int, string>>
     */
    protected function mohistMap(): array
    {
        return Cache::remember($this->cacheKey('mohist:map'), self::CACHE_TTL, function () {
            $xml = $this->getText('Mohist', [
                'https://repo1.maven.org/maven2/com/mohistmc/mohist/maven-metadata.xml',
                'https://repo.maven.apache.org/maven2/com/mohistmc/mohist/maven-metadata.xml',
            ]);

            if ($xml === null) {
                return [];
            }

            preg_match_all('#<version>\s*([^<\s]+)\s*</version>#i', $xml, $matches);

            $map = [];

            foreach ($matches[1] ?? [] as $artifact) {
                // Artifacts look like "1.12.2-291" or "1.20.1-123-server".
                if (!preg_match('#^(1\.[0-9]+(?:\.[0-9]+)?)-(.+)$#', $artifact, $parts)) {
                    continue;
                }

                $map[$parts[1]][] = $artifact;
            }

            // Newest Minecraft version first, newest build first inside it.
            foreach ($map as $version => $artifacts) {
                $map[$version] = array_values(array_reverse($artifacts));
            }

            uksort($map, fn ($a, $b) => version_compare($this->numeric($b), $this->numeric($a)));

            return $map;
        });
    }

    /**
     * Maven Central download candidates for one Mohist artifact version.
     *
     * @return array<int, string>
     */
    protected function mohistMavenDownloads(string $artifact): array
    {
        $artifact = rawurlencode($artifact);

        return [
            sprintf('https://repo1.maven.org/maven2/com/mohistmc/mohist/%1$s/mohist-%1$s-server.jar', $artifact),
            sprintf('https://repo1.maven.org/maven2/com/mohistmc/mohist/%1$s/mohist-%1$s.jar', $artifact),
        ];
    }

    /**
     * MohistMC API download candidates, used as a fallback for versions the
     * Maven mirror does not carry.
     *
     * @return array<int, string>
     */
    protected function mohistApiDownloads(string $version, string $build, bool $includeLatest = false): array
    {
        $version = rawurlencode($version);
        $build = rawurlencode($build);

        $urls = [
            sprintf('https://api.mohistmc.com/api/v2/projects/mohist/%s/builds/%s/download', $version, $build),
            sprintf('https://api.mohistmc.com/project/mohist/%s/builds/%s/download', $version, $build),
        ];

        if ($includeLatest || $build === 'latest') {
            $urls[] = sprintf('https://api.mohistmc.com/api/v2/projects/mohist/%s/builds/latest/download', $version);
        }

        return $urls;
    }

    /**
     * Plain text (non JSON) fetch used for Maven metadata. Returns null when no
     * endpoint answered, so the caller can fall back instead of erroring out.
     *
     * @param array<int, string> $urls
     */
    protected function getText(string $label, array $urls): ?string
    {
        foreach ($urls as $url) {
            try {
                $response = Http::timeout(15)
                    ->connectTimeout(8)
                    ->withHeaders(['User-Agent' => 'Pterodactyl-VersionManager/1.0'])
                    ->get($url);
            } catch (\Throwable $exception) {
                Log::warning('Version Manager: request to ' . $url . ' failed: ' . $exception->getMessage());

                continue;
            }

            if (!$response->successful()) {
                Log::warning('Version Manager: ' . $url . ' returned ' . $response->status());

                continue;
            }

            $body = (string) $response->body();

            if ($body !== '') {
                return $body;
            }
        }

        Log::warning('Version Manager: no text endpoint answered for ' . $label);

        return null;
    }

    /**
     * Legacy Mohist builds. The API has shipped several shapes over time and only some
     * of them carry a usable jar URL, so the download link is validated and
     * rebuilt from the documented pattern when it is missing or points at a web
     * page instead of a file.
     */
    protected function mohistBuilds(array $definition, string $version): array
    {
        $data = $this->get($definition['name'], [
            'https://mohistmc.com/api/v2/projects/mohist/' . rawurlencode($version) . '/builds',
            'https://api.mohistmc.com/project/mohist/' . rawurlencode($version) . '/builds',
        ], 'builds');

        $builds = Arr::get($data, 'builds', Arr::isList($data) ? $data : []);
        $map = [];

        foreach (array_reverse((array) $builds) as $build) {
            // The first entry after reversing is the newest build, and Mohist
            // documents a "latest" download endpoint that is always valid for it.
            $newest = empty($map);

            if (!is_array($build)) {
                $id = trim((string) $build);

                if ($id !== '') {
                    $map[$id] = $this->mohistDownloads($version, $id, $newest);
                }

                continue;
            }

            // Prefer the human readable build number. The git sha is only used
            // when nothing else identifies the build.
            $id = (string) (
                Arr::get($build, 'number')
                ?? Arr::get($build, 'build')
                ?? Arr::get($build, 'buildNumber')
                ?? Arr::get($build, 'id')
                ?? Arr::get($build, 'gitSha')
                ?? ''
            );

            if ($id === '') {
                continue;
            }

            $url = Arr::get($build, 'url')
                ?? Arr::get($build, 'originUrl')
                ?? Arr::get($build, 'downloadUrl')
                ?? Arr::get($build, 'download.url');

            // Some responses identify a build by name (mohist-1.19.2-123-server)
            // instead of a number, and that name also works in download URLs.
            $name = trim((string) (Arr::get($build, 'name') ?? Arr::get($build, 'fileName') ?? ''));

            // Keep every plausible link: the one from the API (validated first,
            // raw second) followed by the documented endpoints. resolveDownload
            // then picks the first that truly returns a jar.
            $map[$id] = array_values(array_unique(array_filter(array_merge(
                $this->mohistDownloads($version, $id, $newest),
                [$this->jarUrl($url), is_string($url) ? $url : null],
                $name !== '' && $name !== $id ? $this->mohistDownloads($version, $name) : []
            ))));
        }

        return $map;
    }

    /**
     * Every known Mohist download endpoint for a build.
     *
     * The "latest" endpoint is the one documented by MohistMC and the only one
     * guaranteed to work, so it goes first whenever the build really is the
     * newest one for that Minecraft version.
     *
     * @return array<int, string>
     */
    protected function mohistDownloads(string $version, string $build, bool $newest = false): array
    {
        $version = rawurlencode($version);
        $build = rawurlencode($build);

        $urls = $newest
            ? [sprintf('https://mohistmc.com/api/v2/projects/mohist/%s/builds/latest/download', $version)]
            : [];

        return array_merge($urls, [
            sprintf('https://mohistmc.com/api/v2/projects/mohist/%s/builds/%s/download', $version, $build),
            sprintf('https://api.mohistmc.com/api/v2/projects/mohist/%s/builds/%s/download', $version, $build),
            sprintf('https://api.mohistmc.com/project/mohist/%s/builds/%s/download', $version, $build),
            sprintf('https://api.mohistmc.com/project/mohist/%s/builds/%s/jar', $version, $build),
        ]);
    }

    /**
     * Accept a URL only when it can plausibly serve a jar. Upstream APIs
     * sometimes hand back a link to their own download page, which would leave
     * the server with an HTML file named server.jar.
     */
    protected function jarUrl(mixed $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }

        if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (str_ends_with($path, '.jar') || str_ends_with($path, '/download') || str_contains($path, '/download/')) {
            return $url;
        }

        return null;
    }

    /**
     * NeoForge publishes a flat list such as "21.1.65", where the first two
     * segments map onto Minecraft 1.21.1. Returns Minecraft version => builds.
     */
    protected function neoforgeMap(): array
    {
        return Cache::remember($this->cacheKey('neoforge:map'), self::CACHE_TTL, function () {
            $data = $this->get('NeoForge', [
                'https://maven.neoforged.net/api/maven/versions/releases/net/neoforged/neoforge',
            ]);

            $map = [];

            foreach (array_reverse(Arr::get($data, 'versions', [])) as $version) {
                $parts = explode('.', (string) $version);

                if (count($parts) < 2) {
                    continue;
                }

                $minecraft = '1.' . $parts[0] . ($parts[1] === '0' ? '' : '.' . $parts[1]);

                $map[$minecraft][] = (string) $version;
            }

            return $map;
        });
    }

    /**
     * Resolve the server jar published by Mojang for a release or snapshot.
     */
    protected function vanillaDownload(string $label, string $version): string
    {
        return Cache::remember($this->cacheKey('vanilla:url:' . md5($version)), self::CACHE_TTL, function () use ($label, $version) {
            $manifest = $this->get($label, [
                'https://launchermeta.mojang.com/mc/game/version_manifest_v2.json',
                'https://piston-meta.mojang.com/mc/game/version_manifest_v2.json',
            ]);

            $entry = collect(Arr::get($manifest, 'versions', []))->firstWhere('id', $version);

            if (!$entry) {
                throw new BadRequestHttpException('That Minecraft version could not be found.');
            }

            $package = $this->get($label, [$entry['url']]);
            $url = Arr::get($package, 'downloads.server.url');

            if (!$url) {
                throw new BadRequestHttpException('Mojang does not publish a server jar for that version.');
            }

            return $url;
        });
    }

    protected function fabricInstallerVersion(): string
    {
        return Cache::remember($this->cacheKey('fabric:installer'), self::CACHE_TTL, function () {
            $data = $this->get('Fabric', ['https://meta.fabricmc.net/v2/versions/installer']);

            $stable = collect($data)->firstWhere('stable', true) ?: collect($data)->first();

            if (!$stable) {
                throw new BadRequestHttpException('The Fabric installer list could not be read.');
            }

            return (string) $stable['version'];
        });
    }

    /**
     * Pull version strings out of the different shapes the PaperMC v3 API uses.
     */
    protected function extractVersionStrings(array $data): array
    {
        $candidates = Arr::get($data, 'versions', $data);
        $versions = [];

        foreach ((array) $candidates as $key => $value) {
            if (is_string($value)) {
                $versions[] = $value;

                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            $id = Arr::get($value, 'version.id') ?? Arr::get($value, 'id') ?? Arr::get($value, 'version');

            if (is_string($id)) {
                $versions[] = $id;

                continue;
            }

            // The v3 API groups releases by family, so the actual versions live
            // inside the value: { "1.21": ["1.21.4", "1.21.3"] }. Never use the
            // family key itself, no builds are published for it.
            $nested = array_values(array_filter($value, 'is_string'));

            if (!empty($nested)) {
                $versions = array_merge($versions, $nested);

                continue;
            }

            if (is_string($key)) {
                $versions[] = $key;
            }
        }

        $versions = array_values(array_unique($versions));

        // Normalise the order across API versions: newest release first.
        usort($versions, fn ($a, $b) => version_compare($this->numeric($b), $this->numeric($a)));

        return $versions;
    }

    protected function numeric(string $version): string
    {
        return preg_replace('/[^0-9.]/', '', $version) ?: '0';
    }

    /**
     * @param array<int, mixed> $builds
     */
    protected function mapBuilds(array $builds, callable $url): array
    {
        $map = [];

        foreach ($builds as $build) {
            $id = (string) $build;

            if ($id === '') {
                continue;
            }

            $map[$id] = $url($id);
        }

        return $map;
    }

    protected function assertVersion(string $core, string $version): void
    {
        if (!in_array($version, $this->versions($core), true)) {
            throw new BadRequestHttpException('That version is not available for the selected core.');
        }
    }

    /**
     * JSON helper that walks a list of endpoints until one answers, with an
     * error message that names the core and the upstream status code.
     *
     * @param array<int, string> $urls
     */
    protected function get(string $label, array $urls, string $what = 'versions'): array
    {
        $problem = null;

        foreach ($urls as $url) {
            $host = parse_url($url, PHP_URL_HOST) ?: $url;

            try {
                $response = Http::timeout(15)
                    ->connectTimeout(8)
                    ->withHeaders(['User-Agent' => 'Pterodactyl-VersionManager/1.0'])
                    ->acceptJson()
                    ->get($url);
            } catch (\Throwable $exception) {
                $problem = sprintf('%s could not be reached (%s).', $host, class_basename($exception));
                Log::warning('Version Manager: request to ' . $url . ' failed: ' . $exception->getMessage());

                continue;
            }

            if (!$response->successful()) {
                $problem = sprintf('%s answered HTTP %d.', $host, $response->status());
                Log::warning('Version Manager: ' . $url . ' returned ' . $response->status());

                continue;
            }

            $json = $response->json();

            if (!is_array($json)) {
                $problem = sprintf('%s returned an unexpected response.', $host);

                continue;
            }

            return $json;
        }

        throw new BadRequestHttpException(sprintf(
            'Could not load %s %s: %s Try again in a moment, or refresh the version cache in the admin area.',
            $label,
            $what,
            $problem ?? 'no endpoint answered.'
        ));
    }

    /**
     * Drop every cached listing, used by the refresh button in the admin area.
     */
    public function flushCache(): void
    {
        // Bumping the generation invalidates version lists, build lists and
        // resolved download URLs in one go. The old entries expire on their own
        // after CACHE_TTL.
        Cache::forever('addons:versions:generation', $this->cacheGeneration() + 1);
    }
}
