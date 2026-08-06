<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Addons\VersionManagerService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Versions\GetVersionsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Versions\InstallVersionRequest;

/**
 * Client side of the "Version Manager" addon: pick a server core and version
 * and have the jar pulled into the server by Wings.
 */
class VersionController extends ClientApiController
{
    public function __construct(
        private DaemonFileRepository $fileRepository,
        private VersionManagerService $service,
    ) {
        parent::__construct();
    }

    /**
     * Cores available on this panel plus the jar this server currently boots.
     */
    public function index(GetVersionsRequest $request, Server $server): JsonResponse
    {
        $this->assertEnabled();

        return new JsonResponse([
            'data' => [
                'cores' => $this->service->cores(),
                'categories' => VersionManagerService::CATEGORIES,
                'jar_file' => $this->jarFile($server),
            ],
        ]);
    }

    /**
     * Versions published for a core, newest first.
     */
    public function versions(GetVersionsRequest $request, Server $server, string $core): JsonResponse
    {
        $this->assertEnabled();

        return new JsonResponse([
            'data' => [
                'core' => $core,
                'versions' => $this->service->versions($core),
            ],
        ]);
    }

    /**
     * Builds published for a version, newest first. May be empty.
     */
    public function builds(GetVersionsRequest $request, Server $server, string $core, string $version): JsonResponse
    {
        $this->assertEnabled();

        return new JsonResponse([
            'data' => [
                'core' => $core,
                'version' => $version,
                'builds' => $this->service->builds($core, $version),
            ],
        ]);
    }

    /**
     * Download the selected jar into the server root, overwriting the current one.
     */
    public function install(InstallVersionRequest $request, Server $server): JsonResponse
    {
        $this->assertEnabled();

        if ($server->isSuspended() || !is_null($server->status)) {
            throw new BadRequestHttpException('This server is not currently available for that action.');
        }

        $wiped = 0;

        // Resolve the download before touching anything, so a bad selection
        // never leaves the user with an empty server directory.
        $download = $this->service->resolveDownload(
            (string) $request->input('core'),
            (string) $request->input('version'),
            $request->input('build'),
            $this->jarFile($server)
        );

        if ($request->boolean('wipe')) {
            $wiped = $this->wipeServerFiles($server);
        }

        // Wings downloads in the background, so the resolved URL is logged to
        // make a failed download traceable afterwards.
        Log::info(sprintf(
            'Version Manager: pulling %s into %s on server %s',
            $download['url'],
            $download['filename'],
            $server->uuid
        ));

        $this->fileRepository->setServer($server)->pull($download['url'], '/', [
            'filename' => $download['filename'],
            'foreground' => false,
            'use_header' => false,
        ]);

        // Wings reports success as soon as it accepted the job, so the file is
        // checked afterwards. Without this a dead upstream link leaves the user
        // with a "downloading" message and no jar at all.
        $verified = $this->verifyDownload($server, $download['filename']);

        Activity::event('server:version.install')
            ->subject($server)
            ->property([
                'core' => $request->input('core'),
                'version' => $request->input('version'),
                'build' => $request->input('build'),
                'file' => $download['filename'],
                'wiped' => $wiped,
            ])
            ->log();

        return new JsonResponse([
            'data' => [
                'label' => $download['label'],
                'filename' => $download['filename'],
                'installer' => $download['installer'],
                'wiped' => $wiped,
                'url' => $download['url'],
                'verified' => $verified,
            ],
        ]);
    }

    /**
     * Wait a few seconds for the jar to show up in the server root.
     *
     * Returns true when a non-empty file with the expected name exists, false
     * when nothing arrived (the download most likely failed), and null when the
     * check itself could not be completed.
     */
    protected function verifyDownload(Server $server, string $filename): ?bool
    {
        $repository = $this->fileRepository->setServer($server);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            sleep(2);

            try {
                $entry = collect($repository->getDirectory('/'))->firstWhere('name', $filename);
            } catch (\Throwable $exception) {
                Log::warning('Version Manager: could not verify ' . $filename . ': ' . $exception->getMessage());

                return null;
            }

            if ($entry && (int) ($entry['size'] ?? 0) > 1024) {
                return true;
            }
        }

        Log::warning(sprintf(
            'Version Manager: %s did not appear on server %s after the pull, the upstream link is probably dead',
            $filename,
            $server->uuid
        ));

        return false;
    }

    /**
     * Delete everything in the server root so the new version starts from a
     * clean directory. Returns the number of top level entries removed.
     */
    protected function wipeServerFiles(Server $server): int
    {
        $repository = $this->fileRepository->setServer($server);

        $entries = collect($repository->getDirectory('/'))
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && $name !== '' && $name !== '.' && $name !== '..')
            ->values()
            ->all();

        if (empty($entries)) {
            return 0;
        }

        // Wings deletes directories recursively, so the top level is enough.
        $repository->deleteFiles('/', $entries);

        Activity::event('server:file.delete')
            ->subject($server)
            ->property(['directory' => '/', 'files' => $entries])
            ->log();

        return count($entries);
    }

    /**
     * The jar this server boots, taken from the SERVER_JARFILE egg variable.
     */
    protected function jarFile(Server $server): string
    {
        $variable = $server->variables->firstWhere('env_variable', 'SERVER_JARFILE');

        $value = $variable?->server_value ?: 'server.jar';

        // Never allow the download to escape the server root.
        $value = basename(str_replace('\\', '/', $value));

        return $value === '' ? 'server.jar' : $value;
    }

    protected function assertEnabled(): void
    {
        if (!$this->service->addonEnabled()) {
            throw new BadRequestHttpException('The Version Manager addon is currently disabled by an administrator.');
        }
    }
}
