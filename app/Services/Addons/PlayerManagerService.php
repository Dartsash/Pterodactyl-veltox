<?php

namespace Pterodactyl\Services\Addons;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * "Minecraft Player Manager" addon.
 *
 * Reads and writes the four player list files a vanilla based Minecraft server
 * keeps in its root directory, so owners never have to touch raw JSON:
 *
 *  - whitelist.json
 *  - ops.json
 *  - banned-players.json
 *  - banned-ips.json
 *
 * The service itself never talks to Wings. It only parses, validates and
 * re-serialises the file contents, and builds the console command that has the
 * same effect on a running server. The controller decides which of the two
 * routes to take.
 */
class PlayerManagerService
{
    public const SETTING_ENABLED = 'settings::addons:players_enabled';
    public const SETTING_LISTS = 'settings::addons:players_lists';
    public const SETTING_LOOKUP = 'settings::addons:players_lookup';

    /** How long a resolved Mojang profile is remembered, in seconds. */
    public const CACHE_TTL = 604800;

    /** Largest file we are willing to parse, in bytes. */
    public const MAX_FILE_SIZE = 2097152;

    /**
     * Every list the addon can manage.
     *
     * subject:   player lists are keyed by name, ban-ip lists by address
     * profile:   whether an entry needs a uuid/name pair resolved for it
     * reason:    whether the list stores a ban reason
     * level:     whether the list stores an operator level
     */
    public const LISTS = [
        'whitelist' => [
            'name' => 'Whitelist',
            'file' => 'whitelist.json',
            'description' => 'Players allowed to join while white-listing is turned on.',
            'subject' => 'player',
            'profile' => true,
            'reason' => false,
            'level' => false,
            'add_command' => 'whitelist add',
            'remove_command' => 'whitelist remove',
            'reload_command' => 'whitelist reload',
            'add_label' => 'Add to whitelist',
            'remove_label' => 'Remove',
        ],
        'ops' => [
            'name' => 'Operators',
            'file' => 'ops.json',
            'description' => 'Players with operator rights. Level 4 unlocks every command.',
            'subject' => 'player',
            'profile' => true,
            'reason' => false,
            'level' => true,
            'add_command' => 'op',
            'remove_command' => 'deop',
            'reload_command' => null,
            'add_label' => 'Give operator',
            'remove_label' => 'Take away',
        ],
        'banned-players' => [
            'name' => 'Banned players',
            'file' => 'banned-players.json',
            'description' => 'Players who cannot join. Banning does not kick an offline player.',
            'subject' => 'player',
            'profile' => true,
            'reason' => true,
            'level' => false,
            'add_command' => 'ban',
            'remove_command' => 'pardon',
            'reload_command' => null,
            'add_label' => 'Ban player',
            'remove_label' => 'Unban',
        ],
        'banned-ips' => [
            'name' => 'Banned IPs',
            'file' => 'banned-ips.json',
            'description' => 'Blocked addresses. Use with care behind shared or mobile networks.',
            'subject' => 'ip',
            'profile' => false,
            'reason' => true,
            'level' => false,
            'add_command' => 'ban-ip',
            'remove_command' => 'pardon-ip',
            'reload_command' => null,
            'add_label' => 'Ban address',
            'remove_label' => 'Unban',
        ],
    ];

    /** Player names Mojang itself allows. */
    public const NAME_PATTERN = '/^[A-Za-z0-9_]{3,16}$/';

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    /**
     * Whether the addon is active. Defaults to on, like the other addons.
     */
    public function addonEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING_ENABLED, '1');
    }

    /**
     * Whether names may be resolved against the Mojang API. When this is off
     * every entry gets an offline mode uuid instead.
     */
    public function lookupEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING_LOOKUP, '1');
    }

    /**
     * Keys of the lists an administrator made available.
     */
    public function enabledListKeys(): array
    {
        $stored = $this->settings->get(self::SETTING_LISTS);

        if (empty($stored)) {
            return array_keys(self::LISTS);
        }

        $decoded = json_decode($stored, true);

        if (!is_array($decoded)) {
            return array_keys(self::LISTS);
        }

        return array_values(array_intersect(array_keys(self::LISTS), $decoded));
    }

    public function setEnabledListKeys(array $keys): void
    {
        $keys = array_values(array_intersect(array_keys(self::LISTS), $keys));

        $this->settings->set(self::SETTING_LISTS, json_encode($keys));
    }

    /**
     * The lists available to a user, shaped for the client API.
     */
    public function lists(): array
    {
        $lists = [];

        foreach ($this->enabledListKeys() as $key) {
            $list = self::LISTS[$key];

            $lists[] = [
                'key' => $key,
                'name' => $list['name'],
                'file' => $list['file'],
                'description' => $list['description'],
                'subject' => $list['subject'],
                'supports_reason' => $list['reason'],
                'supports_level' => $list['level'],
                'add_label' => $list['add_label'],
                'remove_label' => $list['remove_label'],
            ];
        }

        return $lists;
    }

    /**
     * Definition of a list, or a 400 when it is unknown or hidden.
     */
    public function assertListAvailable(string $list): array
    {
        if (!array_key_exists($list, self::LISTS) || !in_array($list, $this->enabledListKeys(), true)) {
            throw new BadRequestHttpException('That player list is not available on this panel.');
        }

        return self::LISTS[$list];
    }

    /**
     * Turn raw file contents into a predictable list of rows.
     *
     * Anything unparsable is treated as an empty list rather than an error: a
     * server that never booted simply has no files yet.
     */
    public function entries(string $list, ?string $contents): array
    {
        $definition = self::LISTS[$list] ?? null;

        if ($definition === null || $contents === null || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if ($definition['subject'] === 'ip') {
                $target = trim((string) Arr::get($entry, 'ip', ''));

                if ($target === '') {
                    continue;
                }

                $rows[] = [
                    'target' => $target,
                    'name' => $target,
                    'uuid' => null,
                    'level' => null,
                    'bypasses_player_limit' => null,
                    'reason' => $this->nullableString(Arr::get($entry, 'reason')),
                    'source' => $this->nullableString(Arr::get($entry, 'source')),
                    'created' => $this->nullableString(Arr::get($entry, 'created')),
                    'expires' => $this->nullableString(Arr::get($entry, 'expires')),
                ];

                continue;
            }

            $name = trim((string) Arr::get($entry, 'name', ''));
            $uuid = trim((string) Arr::get($entry, 'uuid', ''));

            if ($name === '' && $uuid === '') {
                continue;
            }

            $rows[] = [
                'target' => $name !== '' ? $name : $uuid,
                'name' => $name !== '' ? $name : $uuid,
                'uuid' => $uuid === '' ? null : $uuid,
                'level' => array_key_exists('level', $entry) ? (int) $entry['level'] : null,
                'bypasses_player_limit' => array_key_exists('bypassesPlayerLimit', $entry)
                    ? (bool) $entry['bypassesPlayerLimit']
                    : null,
                'reason' => $this->nullableString(Arr::get($entry, 'reason')),
                'source' => $this->nullableString(Arr::get($entry, 'source')),
                'created' => $this->nullableString(Arr::get($entry, 'created')),
                'expires' => $this->nullableString(Arr::get($entry, 'expires')),
            ];
        }

        // Newest bans first, everything else alphabetically.
        if ($definition['reason']) {
            usort($rows, fn ($a, $b) => strcmp((string) $b['created'], (string) $a['created']));
        } else {
            usort($rows, fn ($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));
        }

        return $rows;
    }

    /**
     * Names seen on this server before, read from usercache.json. Used to offer
     * suggestions instead of making the user type a name from memory.
     */
    public function knownPlayers(?string $contents): array
    {
        if ($contents === null || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return [];
        }

        $names = [];

        foreach ($decoded as $entry) {
            $name = is_array($entry) ? trim((string) Arr::get($entry, 'name', '')) : '';

            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return array_slice($names, 0, 200);
    }

    /**
     * Validate user input for a list and normalise it.
     *
     * @return array{target: string, uuid: string|null}
     */
    public function normaliseTarget(string $list, string $target): array
    {
        $definition = $this->assertListAvailable($list);
        $target = trim($target);

        if ($definition['subject'] === 'ip') {
            if (filter_var($target, FILTER_VALIDATE_IP) === false) {
                throw new BadRequestHttpException('That is not a valid IP address.');
            }

            return ['target' => $target, 'uuid' => null];
        }

        if (!preg_match(self::NAME_PATTERN, $target)) {
            throw new BadRequestHttpException(
                'A Minecraft name is 3 to 16 characters long and may only contain letters, numbers and underscores.'
            );
        }

        return ['target' => $target, 'uuid' => $definition['profile'] ? $this->resolveUuid($target) : null];
    }

    /**
     * Add an entry to a decoded list and return the new file contents.
     *
     * Existing entries for the same subject are replaced, so editing a ban
     * reason or an operator level works through the same path as adding.
     */
    public function addEntry(string $list, string $contents, array $input): string
    {
        $definition = $this->assertListAvailable($list);
        $decoded = $this->decodeRaw($contents);

        $target = (string) $input['target'];
        $entry = $definition['subject'] === 'ip'
            ? ['ip' => $target]
            : ['uuid' => (string) ($input['uuid'] ?? $this->offlineUuid($target)), 'name' => $target];

        if ($definition['level']) {
            $level = (int) ($input['level'] ?? 4);
            $entry['level'] = max(1, min(4, $level));
            $entry['bypassesPlayerLimit'] = (bool) ($input['bypasses_player_limit'] ?? false);
        }

        if ($definition['reason']) {
            $entry['created'] = $this->timestamp();
            $entry['source'] = (string) ($input['source'] ?? 'Panel');
            $entry['expires'] = 'forever';
            $entry['reason'] = $this->trimReason($input['reason'] ?? null);
        }

        $kept = [];

        foreach ($decoded as $existing) {
            if (!$this->matches($definition, $existing, $target)) {
                $kept[] = $existing;
            }
        }

        $kept[] = $entry;

        return $this->encode($kept);
    }

    /**
     * Remove every entry matching the subject and return the new contents.
     */
    public function removeEntry(string $list, string $contents, string $target): string
    {
        $definition = $this->assertListAvailable($list);
        $decoded = $this->decodeRaw($contents);

        $kept = [];

        foreach ($decoded as $existing) {
            if (!$this->matches($definition, $existing, $target)) {
                $kept[] = $existing;
            }
        }

        return $this->encode($kept);
    }

    /**
     * The console command that has the same effect on a running server.
     */
    public function command(string $list, string $action, string $target, ?string $reason = null): string
    {
        $definition = $this->assertListAvailable($list);

        $command = $action === 'add' ? $definition['add_command'] : $definition['remove_command'];
        $command .= ' ' . $target;

        if ($action === 'add' && $definition['reason']) {
            $reason = $this->trimReason($reason);

            if ($reason !== '') {
                $command .= ' ' . $reason;
            }
        }

        return $command;
    }

    /**
     * Command that makes a running server re-read the file, when one exists.
     */
    public function reloadCommand(string $list): ?string
    {
        return self::LISTS[$list]['reload_command'] ?? null;
    }

    public function file(string $list): string
    {
        return $this->assertListAvailable($list)['file'];
    }

    /**
     * Look up the real uuid of a name, falling back to the offline mode uuid.
     *
     * A missing or unreachable Mojang API must never block the action: an
     * offline mode server does not care about the value at all, and an online
     * mode server rewrites the file itself as soon as the player joins.
     */
    public function resolveUuid(string $name): string
    {
        if (!$this->lookupEnabled()) {
            return $this->offlineUuid($name);
        }

        $key = 'addons:players:uuid:' . strtolower($name);

        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $uuid = $this->fetchMojangUuid($name) ?? $this->offlineUuid($name);

        Cache::put($key, $uuid, self::CACHE_TTL);

        return $uuid;
    }

    /**
     * Ask Mojang for the uuid of a name. Null when the name is unknown or the
     * API could not be reached.
     */
    protected function fetchMojangUuid(string $name): ?string
    {
        $url = 'https://api.mojang.com/users/profiles/minecraft/' . rawurlencode($name);

        try {
            $context = stream_context_create([
                'http' => ['timeout' => 5, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"],
            ]);

            $body = @file_get_contents($url, false, $context);
        } catch (\Throwable $exception) {
            Log::warning('Player Manager: profile lookup for ' . $name . ' failed: ' . $exception->getMessage());

            return null;
        }

        if (!is_string($body) || trim($body) === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        $id = is_array($decoded) ? (string) Arr::get($decoded, 'id', '') : '';

        if (!preg_match('/^[0-9a-f]{32}$/i', $id)) {
            return null;
        }

        return $this->dashUuid($id);
    }

    /**
     * The uuid an offline mode server generates for a name: a name based (v3)
     * uuid of "OfflinePlayer:<name>".
     */
    public function offlineUuid(string $name): string
    {
        $hash = md5('OfflinePlayer:' . $name, true);

        // Force the version (3) and variant bits, exactly like Java does.
        $hash[6] = chr((ord($hash[6]) & 0x0f) | 0x30);
        $hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80);

        return $this->dashUuid(bin2hex($hash));
    }

    protected function dashUuid(string $hex): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * Whether a raw file entry describes the given subject.
     */
    protected function matches(array $definition, mixed $entry, string $target): bool
    {
        if (!is_array($entry)) {
            return false;
        }

        if ($definition['subject'] === 'ip') {
            return strcasecmp(trim((string) Arr::get($entry, 'ip', '')), $target) === 0;
        }

        return strcasecmp(trim((string) Arr::get($entry, 'name', '')), $target) === 0
            || strcasecmp(trim((string) Arr::get($entry, 'uuid', '')), $target) === 0;
    }

    protected function decodeRaw(?string $contents): array
    {
        if ($contents === null || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            // A broken file would otherwise be silently emptied, so refuse.
            throw new BadRequestHttpException(
                'The file could not be read as JSON. Fix or delete it in the file manager first.'
            );
        }

        // json_decode turns a JSON object into an associative array; the game
        // only ever accepts a list here.
        return array_values(array_filter($decoded, 'is_array'));
    }

    /**
     * Minecraft writes these files as pretty printed JSON without escaped
     * slashes, so matching that keeps diffs in the file manager readable.
     */
    protected function encode(array $entries): string
    {
        if (empty($entries)) {
            return "[]\n";
        }

        return json_encode(
            array_values($entries),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n";
    }

    protected function timestamp(): string
    {
        return date('Y-m-d H:i:s O');
    }

    protected function trimReason(mixed $reason): string
    {
        $reason = trim((string) ($reason ?? ''));

        if ($reason === '') {
            return 'Banned by an operator.';
        }

        // Newlines would corrupt the console command built from this value.
        $reason = preg_replace('/\s+/u', ' ', $reason) ?? $reason;

        return mb_substr($reason, 0, 200);
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
