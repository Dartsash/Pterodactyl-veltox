<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Addons\ModInstallerService;
use Pterodactyl\Services\Addons\RemoteDownloadResolver;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Mods\GetModsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Mods\DeleteModRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Mods\InstallModRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Mods\ToggleModRequest;

/**
 * Client side of the "Mod Installer" addon: browse Modrinth and install mods
 * straight into the server's /mods directory.
 */
class ModController extends ClientApiController
{
    public function __construct(
        private DaemonFileRepository $fileRepository,
        private ModInstallerService $service,
        private RemoteDownloadResolver $downloads,
    ) {
        parent::__construct();
    }

    /**
     * Everything the page needs on load: what the server runs, which mods are
     * already on disk, and the filter options.
     */
    public function index(GetModsRequest $request, Server $server): JsonResponse
    {
        $this->assertEnabled();

        $detected = $this->service->detect($server);

        // The egg told us nothing useful, so look at what is actually installed.
        if ($detected['loader'] === null || $detected['game_version'] === null) {
            $detected = $this->service->refineDetection(
                $detected,
                fn (string $path) => $this->fileRepository->setServer($server)->getDirectory($path)
            );
        }

        return new JsonResponse(['data' => [
            'detected' => $detected,
            'loaders' => ModInstallerService::LOADERS,
            'sorts' => ModInstallerService::SORTS,
            'game_versions' => $this->service->gameVersions(),
            'directory' => ModInstallerService::DIRECTORY,
            'allow_client_mods' => $this->service->allowClientMods(),
            'installed' => $this->installed($server),
        ]]);
    }

    /**
     * Modrinth search. Filters are query parameters so the result is cacheable.
     */
    public function search(GetModsRequest $request, Server $server): JsonResponse
    {
        $this->assertEnabled();

        $results = $this->service->search(
            $this->stringInput($request->query('q')),
            $this->stringInput($request->query('loader')),
            $this->stringInput($request->query('game_version')),
            $this->stringInput($request->query('sort')) ?? 'relevance'
        );

        return new JsonResponse(['data' => $results]);
    }

    /**
     * Installable builds of one project, filtered to the server's loader and
     * game version unless the client asked for something else.
     */
    public function versions(GetModsRequest $request, Server $server, string $slug): JsonResponse
    {
        $this->assertEnabled();

        return new JsonResponse(['data' => $this->service->versions(
            $slug,
            $this->stringInput($request->query('loader')),
            $this->stringInput($request->query('game_version'))
        )]);
    }

    /**
     * Pulls the chosen build, plus its required dependencies when asked, into
     * the /mods directory.
     */
    public function install(InstallModRequest $request, Server $server, string $slug): JsonResponse
    {
        $this->assertEnabled();

        $resolved = $this->service->resolve(
            $slug,
            $this->stringInput($request->input('version')),
            $this->stringInput($request->input('loader')),
            $this->stringInput($request->input('game_version')),
            $request->boolean('dependencies')
        );

        $repository = $this->fileRepository->setServer($server);
        $installed = [];

        foreach ($resolved['files'] as $file) {
            // Wings will not follow redirects, so hand it the final URL. The
            // resolver also refuses to aim the daemon at a private address.
            $url = $this->downloads->resolve($file['url']);

            try {
                $repository->pull($url, ModInstallerService::DIRECTORY, [
                    'filename' => $file['filename'],
                    'foreground' => true,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Mod Installer: the daemon refused to download a mod.', [
                    'server_id' => $server->id,
                    'mod' => $file['slug'],
                    'url' => $url,
                    'exception' => $exception->getMessage(),
                ]);

                throw new DisplayException(sprintf(
                    'Could not download %s: %s',
                    $file['filename'],
                    $exception->getMessage() ?: 'no response from the daemon'
                ));
            }

            // A previous install may have left a disabled copy behind, which
            // would leave both X.jar and X.jar.disabled in /mods.
            try {
                $repository->deleteFiles(ModInstallerService::DIRECTORY, [
                    $file['filename'] . ModInstallerService::DISABLED_SUFFIX,
                ]);
            } catch (\Throwable $exception) {
                // Nothing to clean up, which is the normal case.
            }

            $installed[] = $file['filename'];

            Activity::event('server:mod.install')
                ->subject($server)
                ->property('mod', $file['title'] !== '' ? $file['title'] : $file['slug'])
                ->property('version', $file['version'])
                ->log();
        }

        return new JsonResponse(['data' => [
            'installed' => $installed,
            'version' => $resolved['version'],
            // Required dependencies Modrinth could not give us a build for. The
            // mod may still refuse to load, so the UI has to say so.
            'unresolved_dependencies' => $resolved['unresolved_dependencies'],
            'files' => $this->installed($server),
        ]]);
    }

    /**
     * Enable or disable a mod by renaming it, so it can be switched off without
     * downloading it again.
     */
    public function state(ToggleModRequest $request, Server $server): JsonResponse
    {
        $this->assertEnabled();

        $file = $this->service->assertManageableFile((string) $request->input('file'));
        $enabled = $request->boolean('enabled');

        $suffix = ModInstallerService::DISABLED_SUFFIX;
        $base = str_ends_with($file, $suffix) ? substr($file, 0, -strlen($suffix)) : $file;

        $from = $enabled ? $base . $suffix : $base;
        $to = $enabled ? $base : $base . $suffix;

        try {
            $this->fileRepository->setServer($server)->renameFiles(
                ModInstallerService::DIRECTORY,
                [['from' => $from, 'to' => $to]]
            );
        } catch (\Throwable $exception) {
            throw new DisplayException(sprintf(
                'Could not %s %s: %s',
                $enabled ? 'enable' : 'disable',
                $from,
                $exception->getMessage() ?: 'the daemon did not respond'
            ));
        }

        Activity::event($enabled ? 'server:mod.enable' : 'server:mod.disable')
            ->subject($server)
            ->property('mod', $base)
            ->log();

        return new JsonResponse(['data' => ['installed' => $this->installed($server)]]);
    }

    /**
     * Deletes a mod jar from /mods.
     */
    public function destroy(DeleteModRequest $request, Server $server): Response
    {
        $this->assertEnabled();

        $file = $this->service->assertManageableFile((string) $request->input('file'));

        try {
            $this->fileRepository->setServer($server)->deleteFiles(ModInstallerService::DIRECTORY, [$file]);
        } catch (\Throwable $exception) {
            throw new DisplayException(sprintf(
                'Could not delete %s: %s',
                $file,
                $exception->getMessage() ?: 'the daemon did not respond'
            ));
        }

        Activity::event('server:mod.delete')->subject($server)->property('mod', $file)->log();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Jars currently sitting in /mods. A missing directory is normal on a
     * server that has never run a modded jar, so it is reported as empty
     * instead of failing the whole page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function installed(Server $server): array
    {
        try {
            $contents = $this->fileRepository->setServer($server)->getDirectory(ModInstallerService::DIRECTORY);
        } catch (\Throwable $exception) {
            Log::debug(sprintf(
                'Mod Installer: could not list %s on server %s: %s',
                ModInstallerService::DIRECTORY,
                $server->uuid,
                $exception->getMessage()
            ));

            return [];
        }

        $suffix = ModInstallerService::DISABLED_SUFFIX;
        $files = [];

        foreach ($contents as $entry) {
            $name = (string) ($entry['name'] ?? '');

            if ($name === '' || !($entry['file'] ?? true)) {
                continue;
            }

            $lower = strtolower($name);
            $disabled = str_ends_with($lower, '.jar' . $suffix);

            // Ignore anything that is not a mod jar, such as a config folder or
            // a leftover README the user dropped in there.
            if (!$disabled && !str_ends_with($lower, '.jar')) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'display_name' => $disabled ? substr($name, 0, -strlen($suffix)) : $name,
                'size' => (int) ($entry['size'] ?? 0),
                'modified' => $entry['modified'] ?? null,
                'enabled' => !$disabled,
            ];
        }

        usort($files, fn (array $a, array $b) => strcasecmp($a['display_name'], $b['display_name']));

        return $files;
    }

    /**
     * Normalises a query or body value into a trimmed string or null.
     */
    private function stringInput(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function assertEnabled(): void
    {
        if (!$this->service->addonEnabled()) {
            throw new DisplayException('The Mod Installer addon is currently disabled by an administrator.');
        }
    }
}
