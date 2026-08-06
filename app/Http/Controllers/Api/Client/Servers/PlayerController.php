<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Support\Arr;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Services\Addons\PlayerManagerService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Players\GetPlayersRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Players\UpdatePlayersRequest;

/**
 * Client side of the "Minecraft Player Manager" addon: manage the whitelist,
 * operators, banned players and banned IPs without opening a single JSON file.
 *
 * While the server is running the change is made with the matching console
 * command, so it takes effect immediately and the game writes the file itself.
 * While the server is stopped the JSON file is edited directly.
 */
class PlayerController extends ClientApiController
{
    public function __construct(
        private DaemonFileRepository $fileRepository,
        private DaemonCommandRepository $commandRepository,
        private DaemonServerRepository $serverRepository,
        private PlayerManagerService $service,
    ) {
        parent::__construct();
    }

    /**
     * Every available list together with its current contents.
     */
    public function index(GetPlayersRequest $request, Server $server): JsonResponse
    {
        $this->assertEnabled();

        $lists = $this->service->lists();
        $entries = [];

        foreach ($lists as $list) {
            $entries[$list['key']] = $this->service->entries(
                $list['key'],
                $this->readFile($server, $list['file'])
            );
        }

        return new JsonResponse([
            'data' => [
                'lists' => $lists,
                'entries' => $entries,
                'known_players' => $this->service->knownPlayers($this->readFile($server, 'usercache.json')),
                'running' => $this->isRunning($server),
            ],
        ]);
    }

    /**
     * Add a player or an address to a list.
     */
    public function store(UpdatePlayersRequest $request, Server $server, string $list): JsonResponse
    {
        $this->assertEnabled();

        $definition = $this->service->assertListAvailable($list);
        $normalised = $this->service->normaliseTarget($list, (string) $request->input('target'));

        $this->apply($server, $list, 'add', $normalised['target'], [
            'target' => $normalised['target'],
            'uuid' => $normalised['uuid'],
            'level' => $request->input('level'),
            'bypasses_player_limit' => $request->boolean('bypasses_player_limit'),
            'reason' => $request->input('reason'),
            'source' => $request->user()->username,
        ]);

        Activity::event('server:player.add')
            ->subject($server)
            ->property(['list' => $list, 'target' => $normalised['target'], 'reason' => $request->input('reason')])
            ->log();

        return $this->listResponse($server, $list, $definition['file']);
    }

    /**
     * Remove a player or an address from a list.
     */
    public function destroy(UpdatePlayersRequest $request, Server $server, string $list): JsonResponse
    {
        $this->assertEnabled();

        $definition = $this->service->assertListAvailable($list);
        $target = trim((string) $request->input('target'));

        if ($target === '') {
            throw new BadRequestHttpException('No player was selected.');
        }

        $this->apply($server, $list, 'remove', $target, []);

        Activity::event('server:player.remove')
            ->subject($server)
            ->property(['list' => $list, 'target' => $target])
            ->log();

        return $this->listResponse($server, $list, $definition['file']);
    }

    /**
     * Perform the change either through the console or through the file.
     */
    protected function apply(Server $server, string $list, string $action, string $target, array $input): void
    {
        if ($server->isSuspended() || !is_null($server->status)) {
            throw new BadRequestHttpException('This server is not currently available for that action.');
        }

        if ($this->isRunning($server) && $this->sendCommand($server, $list, $action, $target, $input)) {
            return;
        }

        $this->editFile($server, $list, $action, $target, $input);
    }

    /**
     * Ask the running server to make the change. Returns false when the command
     * could not be delivered, so the caller can fall back to the file.
     */
    protected function sendCommand(Server $server, string $list, string $action, string $target, array $input): bool
    {
        $commands = [$this->service->command($list, $action, $target, $input['reason'] ?? null)];

        $reload = $this->service->reloadCommand($list);

        if ($reload !== null) {
            $commands[] = $reload;
        }

        try {
            $this->commandRepository->setServer($server)->send($commands);
        } catch (\Throwable $exception) {
            Log::warning('Player Manager: console command failed, editing the file instead: ' . $exception->getMessage());

            return false;
        }

        // The game rewrites the file asynchronously, give it a moment so the
        // response already contains the new state.
        sleep(1);

        return true;
    }

    /**
     * Edit the JSON file directly, used while the server is stopped.
     */
    protected function editFile(Server $server, string $list, string $action, string $target, array $input): void
    {
        $file = $this->service->file($list);
        $contents = $this->readFile($server, $file) ?? '[]';

        $updated = $action === 'add'
            ? $this->service->addEntry($list, $contents, $input)
            : $this->service->removeEntry($list, $contents, $target);

        if ($updated === $contents) {
            return;
        }

        $this->fileRepository->setServer($server)->putContent($file, $updated);
    }

    /**
     * Fresh contents of a single list after a change.
     */
    protected function listResponse(Server $server, string $list, string $file): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'key' => $list,
                'entries' => $this->service->entries($list, $this->readFile($server, $file)),
                'running' => $this->isRunning($server),
            ],
        ]);
    }

    /**
     * File contents, or null when the file does not exist yet. A server that
     * never booted has none of these files, which is not an error.
     */
    protected function readFile(Server $server, string $file): ?string
    {
        try {
            return $this->fileRepository->setServer($server)->getContent(
                $file,
                PlayerManagerService::MAX_FILE_SIZE
            );
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Whether the server is currently running, so console commands can be used.
     */
    protected function isRunning(Server $server): bool
    {
        try {
            $details = $this->serverRepository->setServer($server)->getDetails();
        } catch (\Throwable $exception) {
            return false;
        }

        return Arr::get($details, 'state') === 'running';
    }

    protected function assertEnabled(): void
    {
        if (!$this->service->addonEnabled()) {
            throw new BadRequestHttpException('The Player Manager addon is currently disabled by an administrator.');
        }
    }
}
