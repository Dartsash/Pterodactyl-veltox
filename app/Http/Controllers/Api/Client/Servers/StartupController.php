<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Servers\StartupCommandService;
use Pterodactyl\Services\Servers\StartupCommandBuilderService;
use Pterodactyl\Repositories\Eloquent\ServerVariableRepository;
use Pterodactyl\Transformers\Api\Client\EggVariableTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\GetStartupRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupCommandRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupVariableRequest;

class StartupController extends ClientApiController
{
    /**
     * StartupController constructor.
     */
    public function __construct(
        private StartupCommandService $startupCommandService,
        private StartupCommandBuilderService $commandBuilder,
        private ServerVariableRepository $repository,
    ) {
        parent::__construct();
    }

    /**
     * Returns the startup information for the server including all the variables.
     */
    public function index(GetStartupRequest $request, Server $server): array
    {
        $startup = $this->startupCommandService->handle($server);

        return $this->fractal->collection(
            $server->variables()->where('user_viewable', true)->get()
        )
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                'startup_command' => $startup,
                'docker_images' => $server->egg->docker_images,
                'raw_startup_command' => $server->startup,
                // Null when an administrator disabled the Startup Editor addon,
                // which makes the editor disappear from the client UI entirely.
                'startup_editor' => !$this->commandBuilder->addonEnabled() ? null : [
                    'options' => $this->commandBuilder->detect($server),
                    'available_options' => $this->commandBuilder->enabledOptions(),
                    'memory_limit' => $this->commandBuilder->memoryLimit($server),
                    // Hand written commands stay locked to administrators.
                    'can_use_manual' => $request->user()->root_admin && $this->commandBuilder->manualAllowed(),
                ],
            ])
            ->toArray();
    }

    /**
     * Updates the server's startup command.
     *
     * The "auto" mode builds the command from a whitelist of safe options and is
     * available to anyone holding the startup update permission. The "manual"
     * mode accepts a raw command and is restricted to administrators, since a
     * free-form command runs inside the server container.
     *
     * @throws \Throwable
     */
    public function command(UpdateStartupCommandRequest $request, Server $server): JsonResponse
    {
        if (!$this->commandBuilder->addonEnabled()) {
            throw new BadRequestHttpException('The Startup Editor addon is currently disabled by an administrator.');
        }

        $original = $server->startup;

        if ($request->input('mode') === 'reset') {
            // Restores whatever the egg defines, discarding every custom flag.
            $startup = $this->commandBuilder->defaultCommand($server);
        } elseif ($request->input('mode') === 'manual') {
            if (!$this->commandBuilder->manualAllowed()) {
                throw new BadRequestHttpException('Custom startup commands are currently disabled by an administrator.');
            }

            if (!$request->user()->root_admin) {
                throw new BadRequestHttpException('Only an administrator may set a custom startup command.');
            }

            $startup = $this->commandBuilder->validateManual($server, (string) $request->input('command', ''));
        } else {
            $startup = $this->commandBuilder->build($server, (array) $request->input('options', []));
        }

        $server->forceFill(['startup' => $startup])->saveOrFail();

        $server = $server->refresh();

        if ($original !== $startup) {
            Activity::event('server:startup.command')
                ->subject($server)
                ->property(['old' => $original, 'new' => $startup])
                ->log();
        }

        return new JsonResponse([
            'data' => [
                'raw_startup_command' => $server->startup,
                'startup_command' => $this->startupCommandService->handle($server),
                'options' => $this->commandBuilder->detect($server),
            ],
        ]);
    }

    /**
     * Updates a single variable for a server.
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UpdateStartupVariableRequest $request, Server $server): array
    {
        $variable = $server->variables()->where('env_variable', $request->input('key'))->first();

        if (is_null($variable) || !$variable->user_viewable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit does not exist.');
        } elseif (!$variable->user_editable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit is read-only.');
        }

        $original = $variable->server_value;

        // Revalidate the variable value using the egg variable specific validation rules for it.
        $this->validate($request, ['value' => $variable->rules]);

        $this->repository->updateOrCreate([
            'server_id' => $server->id,
            'variable_id' => $variable->id,
        ], [
            'variable_value' => $request->input('value') ?? '',
        ]);

        $variable = $variable->refresh();
        $variable->server_value = $request->input('value');

        $startup = $this->startupCommandService->handle($server);

        if ($original !== $request->input('value')) {
            Activity::event('server:startup.edit')
                ->subject($variable)
                ->property([
                    'variable' => $variable->env_variable,
                    'old' => $original,
                    'new' => $request->input('value') ?? '',
                ])
                ->log();
        }

        return $this->fractal->item($variable)
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                'startup_command' => $startup,
                'raw_startup_command' => $server->startup,
            ])
            ->toArray();
    }
}
