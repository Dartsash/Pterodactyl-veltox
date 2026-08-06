<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Servers\ServerPropertiesService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Properties\GetServerPropertiesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Properties\UpdateServerPropertiesRequest;

/**
 * Client side of the "Config Editor" addon: a friendly editor for the
 * server.properties file that never touches keys outside of its whitelist.
 */
class ServerPropertiesController extends ClientApiController
{
    public function __construct(
        private DaemonFileRepository $fileRepository,
        private ServerPropertiesService $service,
    ) {
        parent::__construct();
    }

    /**
     * Returns the editable fields together with the values currently stored in
     * the server.properties file.
     */
    public function index(GetServerPropertiesRequest $request, Server $server): JsonResponse
    {
        $this->assertEnabled();

        try {
            $contents = $this->fileRepository->setServer($server)->getContent(
                ServerPropertiesService::FILE,
                config('pterodactyl.files.max_edit_size')
            );
        } catch (\Throwable $exception) {
            // Most likely there is no file yet (server never booted, or this is
            // not a Minecraft egg), but a daemon or permission problem lands
            // here too, so record it instead of silently reporting "no file".
            Log::warning(sprintf(
                'Config Editor: could not read %s on server %s: %s',
                ServerPropertiesService::FILE,
                $server->uuid,
                $exception->getMessage()
            ));

            return new JsonResponse([
                'data' => [
                    'available' => false,
                    'groups' => ServerPropertiesService::GROUPS,
                    'fields' => $this->service->fields(),
                    'values' => [],
                ],
            ]);
        }

        // Parsing must never turn into a bare 500: a broken file should say what
        // is wrong instead of "an unexpected error was encountered".
        try {
            $fields = $this->service->fields();
            $values = $this->service->readable($contents);
        } catch (\Throwable $exception) {
            Log::error(sprintf(
                'Config Editor: failed to parse %s on server %s: %s in %s:%d',
                ServerPropertiesService::FILE,
                $server->uuid,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));

            throw new BadRequestHttpException(
                'The server.properties file could not be read: ' . $exception->getMessage()
            );
        }

        return new JsonResponse([
            'data' => [
                'available' => true,
                'groups' => ServerPropertiesService::GROUPS,
                'fields' => $fields,
                'values' => $values,
            ],
        ]);
    }

    /**
     * Writes the submitted values back into server.properties, leaving comments,
     * ordering and any unknown keys untouched.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(UpdateServerPropertiesRequest $request, Server $server): JsonResponse
    {
        $this->assertEnabled();

        // A value read out of an older server.properties can be spelled
        // differently to the option we offer ("largeBiomes", "DEFAULT", or the
        // numeric difficulty old versions wrote). Fold those onto the canonical
        // option before validating, otherwise saving an untouched form fails.
        $request->merge([
            'values' => $this->service->normaliseInput((array) $request->input('values', [])),
        ]);

        $this->validate($request, $this->service->rules());

        $repository = $this->fileRepository->setServer($server);

        try {
            $contents = $repository->getContent(
                ServerPropertiesService::FILE,
                config('pterodactyl.files.max_edit_size')
            );
        } catch (\Throwable $exception) {
            throw new BadRequestHttpException(
                'The server.properties file could not be read. Start the server once so it gets created.'
            );
        }

        $values = (array) $request->input('values', []);
        $updated = $this->service->merge($contents, $values);

        if ($updated !== $contents) {
            $repository->putContent(ServerPropertiesService::FILE, $updated);

            Activity::event('server:properties.update')
                ->subject($server)
                ->property(['keys' => array_keys(array_intersect_key($values, ServerPropertiesService::FIELDS))])
                ->log();
        }

        return new JsonResponse([
            'data' => [
                'available' => true,
                'groups' => ServerPropertiesService::GROUPS,
                'fields' => $this->service->fields(),
                'values' => $this->service->readable($updated),
            ],
        ]);
    }

    private function assertEnabled(): void
    {
        if (!$this->service->addonEnabled()) {
            throw new BadRequestHttpException('The Config Editor addon is currently disabled by an administrator.');
        }
    }
}
