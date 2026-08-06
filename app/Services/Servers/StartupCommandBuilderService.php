<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Builds a server startup command from a whitelist of safe options.
 *
 * The automatic mode never lets a user supply free-form text: every part of
 * the resulting command comes from the constants below, so it is impossible to
 * smuggle in shell commands, extra binaries or downloads. Only the memory value
 * is numeric input, and it is clamped to the server's own memory limit.
 */
class StartupCommandBuilderService
{
    /**
     * Persistent settings driving the "Startup Editor" addon shown under
     * /admin/addons. Everything defaults to on so the addon works out of the box.
     */
    public const SETTING_ENABLED = 'settings::addons:startup_editor_enabled';

    public const SETTING_MANUAL = 'settings::addons:startup_editor_manual';

    public const SETTING_OPTIONS = 'settings::addons:startup_editor_options';

    /**
     * Every option an administrator may offer to server owners.
     */
    public const AVAILABLE_OPTIONS = ['memory', 'aikar', 'ignore_java_version', 'utf8', 'console_compat', 'nogui'];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * Whether the Startup Editor addon is switched on at all.
     */
    public function addonEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING_ENABLED, '1');
    }

    /**
     * Whether administrators may write a raw startup command by hand.
     */
    public function manualAllowed(): bool
    {
        return (bool) $this->settings->get(self::SETTING_MANUAL, '1');
    }

    /**
     * The subset of options the administrator exposes to server owners.
     */
    public function enabledOptions(): array
    {
        $stored = $this->settings->get(self::SETTING_OPTIONS);

        if (is_null($stored) || $stored === '') {
            return self::AVAILABLE_OPTIONS;
        }

        $decoded = json_decode($stored, true);

        if (!is_array($decoded)) {
            return self::AVAILABLE_OPTIONS;
        }

        return array_values(array_intersect(self::AVAILABLE_OPTIONS, $decoded));
    }

    /**
     * Aikar's recommended G1GC tuning flags (the widely used set for heaps
     * under ~12GB). Safe: these only configure the JVM garbage collector.
     */
    public const AIKAR_FLAGS = '-XX:+UseG1GC -XX:+ParallelRefProcEnabled -XX:MaxGCPauseMillis=200 '
        . '-XX:+UnlockExperimentalVMOptions -XX:+DisableExplicitGC -XX:+AlwaysPreTouch '
        . '-XX:G1NewSizePercent=30 -XX:G1MaxNewSizePercent=40 -XX:G1HeapRegionSize=8M '
        . '-XX:G1ReservePercent=20 -XX:G1HeapWastePercent=5 -XX:G1MixedGCCountTarget=4 '
        . '-XX:InitiatingHeapOccupancyPercent=15 -XX:G1MixedGCLiveThresholdPercent=90 '
        . '-XX:G1RSetUpdatingPauseTimePercent=5 -XX:SurvivorRatio=32 -XX:+PerfDisableSharedMem '
        . '-XX:MaxTenuringThreshold=1 -Dusing.aikars.flags=https://mcflags.emc.gs -Daikars.new.flags=true';

    /**
     * Used when the user does not pin an explicit heap size.
     */
    public const DEFAULT_MEMORY_FLAGS = '-Xms128M -XX:MaxRAMPercentage=95.0';

    /**
     * Simple on/off system properties. Every value is a constant string.
     */
    public const TOGGLE_FLAGS = [
        'ignore_java_version' => '-DPaper.IgnoreJavaVersion=true',
        'utf8' => '-Dfile.encoding=UTF-8',
        'console_compat' => '-Dterminal.jline=false -Dterminal.ansi=true',
    ];

    /**
     * Characters that would allow chaining or substituting another command.
     */
    private const FORBIDDEN_PATTERN = '/[;&|`$<>\n\r\\\\]/';

    /**
     * Assemble a startup command from the whitelisted options.
     */
    public function build(Server $server, array $options): string
    {
        // Anything the administrator turned off is dropped before we build.
        $options = array_intersect_key($options, array_flip($this->enabledOptions()));

        $parts = ['java'];

        $memory = $this->normalizeMemory($server, $options['memory'] ?? null);
        $parts[] = is_null($memory)
            ? self::DEFAULT_MEMORY_FLAGS
            : sprintf('-Xms%dM -Xmx%dM', $memory, $memory);

        if (!empty($options['aikar'])) {
            $parts[] = self::AIKAR_FLAGS;
        }

        foreach (self::TOGGLE_FLAGS as $key => $flag) {
            if (!empty($options[$key])) {
                $parts[] = $flag;
            }
        }

        // The jar token is taken from the server's current command so we keep
        // whatever the egg configured (usually the {{SERVER_JARFILE}} variable).
        $parts[] = '-jar ' . $this->jarToken($server);

        if (!empty($options['nogui'])) {
            $parts[] = '--nogui';
        }

        return implode(' ', $parts);
    }

    /**
     * The startup command this server's egg ships with. Used by the "reset to
     * default" button, and it comes straight from the egg, so a user can never
     * influence its contents.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function defaultCommand(Server $server): string
    {
        $default = trim((string) ($server->egg->startup ?? ''));

        if ($default === '') {
            throw new DisplayException('This server\'s egg does not define a default startup command.');
        }

        return $default;
    }

    /**
     * Inspect the current startup command so the UI can pre-tick the boxes.
     */
    public function detect(Server $server): array
    {
        $startup = (string) $server->startup;

        $memory = null;
        if (preg_match('/-Xmx(\d+)\s*([MmGg])/', $startup, $matches)) {
            $memory = (int) $matches[1] * (strtolower($matches[2]) === 'g' ? 1024 : 1);
        }

        $detected = [
            'memory' => $memory,
            'aikar' => str_contains($startup, 'aikars.flags') || str_contains($startup, '-XX:G1MixedGCCountTarget'),
            'nogui' => str_contains($startup, '--nogui'),
        ];

        foreach (self::TOGGLE_FLAGS as $key => $flag) {
            // Compare on the first token so partial flag sets still register.
            $needle = explode(' ', $flag)[0];
            $detected[$key] = str_contains($startup, $needle);
        }

        return $detected;
    }

    /**
     * Validate a raw, hand written startup command (administrators only).
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function validateManual(Server $server, string $command): string
    {
        $command = trim(preg_replace('/\s+/', ' ', $command) ?? '');

        if ($command === '') {
            throw new DisplayException('The startup command cannot be empty.');
        }

        if (mb_strlen($command) > 2048) {
            throw new DisplayException('The startup command is too long (2048 characters maximum).');
        }

        if (!preg_match('/^java(\s|$)/', $command)) {
            throw new DisplayException('The startup command must start with "java".');
        }

        if (preg_match(self::FORBIDDEN_PATTERN, $command)) {
            throw new DisplayException('The startup command may not contain shell characters such as ; & | ` $ < > or backslashes.');
        }

        if (!str_contains($command, '-jar')) {
            throw new DisplayException('The startup command must contain a "-jar" argument.');
        }

        // Never let someone request more heap than the server is allowed to use.
        if ($server->memory > 0 && preg_match('/-Xmx(\d+)\s*([MmGg])/', $command, $matches)) {
            $requested = (int) $matches[1] * (strtolower($matches[2]) === 'g' ? 1024 : 1);

            if ($requested > $server->memory) {
                throw new DisplayException(sprintf(
                    'The requested heap size (-Xmx %dMB) exceeds this server\'s memory limit of %dMB.',
                    $requested,
                    $server->memory
                ));
            }
        }

        return $command;
    }

    /**
     * The largest heap size this server may use, in MB. Null means unlimited.
     */
    public function memoryLimit(Server $server): ?int
    {
        return $server->memory > 0 ? $server->memory : null;
    }

    /**
     * Clamp the requested heap size to the server's limit.
     */
    private function normalizeMemory(Server $server, mixed $memory): ?int
    {
        if (is_null($memory) || $memory === '' || $memory === false) {
            return null;
        }

        $memory = (int) $memory;

        if ($memory < 128) {
            return null;
        }

        if ($server->memory > 0 && $memory > $server->memory) {
            $memory = $server->memory;
        }

        return $memory;
    }

    /**
     * Pull the jar argument out of the existing command, falling back to the
     * egg's standard variable. The result is whitelisted against a strict
     * pattern so it can never introduce new arguments.
     */
    private function jarToken(Server $server): string
    {
        $default = '{{SERVER_JARFILE}}';

        if (preg_match('/-jar\s+(\S+)/', (string) $server->startup, $matches)) {
            $candidate = $matches[1];

            if (preg_match('/^[A-Za-z0-9_.\-\/{}]+$/', $candidate)) {
                return $candidate;
            }
        }

        return $default;
    }
}
