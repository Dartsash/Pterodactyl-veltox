<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Backs the "Config Editor" addon.
 *
 * Reads and writes a Minecraft server.properties file through a fixed whitelist
 * of keys. Anything the whitelist does not know about (comments, custom keys,
 * ordering) is preserved untouched, so the file keeps working even if the user
 * hand edited it through the file manager.
 */
class ServerPropertiesService
{
    public const SETTING_ENABLED = 'settings::addons:config_editor_enabled';

    /**
     * JSON array of the property keys administrators expose to server owners.
     * Empty or invalid means "show everything".
     */
    public const SETTING_FIELDS = 'settings::addons:config_editor_fields';

    public const FILE = '/server.properties';

    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_SELECT = 'select';

    /**
     * Only these keys may be edited from the panel. Everything else in the file
     * is left exactly as it was written.
     */
    public const FIELDS = [
        'motd' => [
            'label' => 'Server MOTD',
            'description' => 'The message shown in the multiplayer server list.',
            'group' => 'general',
            'type' => self::TYPE_TEXT,
            'max' => 150,
        ],
        'max-players' => [
            'label' => 'Max players',
            'description' => 'How many players may be connected at the same time.',
            'group' => 'general',
            'type' => self::TYPE_NUMBER,
            'min' => 1,
            'max' => 1000,
        ],
        'gamemode' => [
            'label' => 'Default game mode',
            'description' => 'Game mode new players start in.',
            'group' => 'general',
            'type' => self::TYPE_SELECT,
            'options' => ['survival', 'creative', 'adventure', 'spectator'],
        ],
        'force-gamemode' => [
            'label' => 'Force game mode',
            'description' => 'Puts players back into the default game mode every time they join.',
            'group' => 'general',
            'type' => self::TYPE_BOOLEAN,
        ],
        'difficulty' => [
            'label' => 'Difficulty',
            'description' => 'How aggressive mobs are and whether hunger drains health.',
            'group' => 'general',
            'type' => self::TYPE_SELECT,
            'options' => ['peaceful', 'easy', 'normal', 'hard'],
        ],
        'hardcore' => [
            'label' => 'Hardcore mode',
            'description' => 'Players are banned to spectator mode when they die.',
            'group' => 'general',
            'type' => self::TYPE_BOOLEAN,
        ],
        'pvp' => [
            'label' => 'Player versus player',
            'description' => 'Lets players damage each other.',
            'group' => 'players',
            'type' => self::TYPE_BOOLEAN,
        ],
        'allow-flight' => [
            'label' => 'Allow flight',
            'description' => 'Needed for most flight mods and some plugins. Off means players get kicked for flying.',
            'group' => 'players',
            'type' => self::TYPE_BOOLEAN,
        ],
        'player-idle-timeout' => [
            'label' => 'Idle timeout (minutes)',
            'description' => 'Kicks players after this many idle minutes. 0 disables the timeout.',
            'group' => 'players',
            'type' => self::TYPE_NUMBER,
            'min' => 0,
            'max' => 1440,
        ],
        'spawn-protection' => [
            'label' => 'Spawn protection (blocks)',
            'description' => 'Radius around spawn only operators may build in. 0 disables it.',
            'group' => 'players',
            'type' => self::TYPE_NUMBER,
            'min' => 0,
            'max' => 1000,
        ],
        'level-name' => [
            'label' => 'World folder',
            'description' => 'Name of the world directory. A new name generates a fresh world on next boot.',
            'group' => 'world',
            'type' => self::TYPE_TEXT,
            'max' => 64,
        ],
        'level-seed' => [
            'label' => 'World seed',
            'description' => 'Only used when the world is generated for the first time.',
            'group' => 'world',
            'type' => self::TYPE_TEXT,
            'max' => 128,
        ],
        'level-type' => [
            'label' => 'World type',
            'description' => 'Terrain generator used for new worlds.',
            'group' => 'world',
            'type' => self::TYPE_SELECT,
            'options' => [
                'minecraft:normal',
                'minecraft:flat',
                'minecraft:large_biomes',
                'minecraft:amplified',
                'minecraft:single_biome_surface',
            ],
            // Mods and plugins (Terra, Terralith, custom world plugins) put their
            // own generator id here, so an unknown value has to survive a save.
            'allow_custom' => true,
        ],
        'allow-nether' => [
            'label' => 'Allow the Nether',
            'description' => 'Turns the Nether dimension on or off.',
            'group' => 'world',
            'type' => self::TYPE_BOOLEAN,
        ],
        'spawn-animals' => [
            'label' => 'Spawn animals',
            'group' => 'world',
            'type' => self::TYPE_BOOLEAN,
        ],
        'spawn-monsters' => [
            'label' => 'Spawn monsters',
            'group' => 'world',
            'type' => self::TYPE_BOOLEAN,
        ],
        'spawn-npcs' => [
            'label' => 'Spawn villagers',
            'group' => 'world',
            'type' => self::TYPE_BOOLEAN,
        ],
        'view-distance' => [
            'label' => 'View distance (chunks)',
            'description' => 'Lower values noticeably reduce CPU and RAM usage. 10 is a good balance.',
            'group' => 'performance',
            'type' => self::TYPE_NUMBER,
            'min' => 2,
            'max' => 32,
        ],
        'simulation-distance' => [
            'label' => 'Simulation distance (chunks)',
            'description' => 'How far away entities and redstone keep ticking. Usually the biggest performance win.',
            'group' => 'performance',
            'type' => self::TYPE_NUMBER,
            'min' => 3,
            'max' => 32,
        ],
        'enable-command-block' => [
            'label' => 'Enable command blocks',
            'group' => 'performance',
            'type' => self::TYPE_BOOLEAN,
        ],
        'online-mode' => [
            'label' => 'Online mode (licence check)',
            'description' => 'Turning this off lets cracked clients join and is unsafe without a proxy or auth plugin.',
            'group' => 'security',
            'type' => self::TYPE_BOOLEAN,
            'warning' => true,
        ],
        'white-list' => [
            'label' => 'Whitelist',
            'description' => 'Only players on the whitelist may join.',
            'group' => 'security',
            'type' => self::TYPE_BOOLEAN,
        ],
        'enforce-whitelist' => [
            'label' => 'Enforce whitelist',
            'description' => 'Kicks players already online when they are not on the whitelist.',
            'group' => 'security',
            'type' => self::TYPE_BOOLEAN,
        ],
    ];

    /**
     * Values a server.properties file may legitimately contain that are not the
     * canonical option we offer, mapped onto the option they mean.
     *
     * Keys are compared lower cased. Minecraft renamed these over the years and
     * very old versions stored the numeric enum, so a file written by any
     * version has to be understood instead of rejected.
     */
    public const ALIASES = [
        'gamemode' => [
            '0' => 'survival',
            '1' => 'creative',
            '2' => 'adventure',
            '3' => 'spectator',
        ],
        'difficulty' => [
            '0' => 'peaceful',
            '1' => 'easy',
            '2' => 'normal',
            '3' => 'hard',
        ],
        'level-type' => [
            'default' => 'minecraft:normal',
            'normal' => 'minecraft:normal',
            'flat' => 'minecraft:flat',
            'largebiomes' => 'minecraft:large_biomes',
            'large_biomes' => 'minecraft:large_biomes',
            'amplified' => 'minecraft:amplified',
            'buffet' => 'minecraft:single_biome_surface',
            'single_biome_surface' => 'minecraft:single_biome_surface',
            // Removed in 1.13 but still sitting in ancient files.
            'customized' => 'minecraft:normal',
            'default_1_1' => 'minecraft:normal',
        ],
    ];

    public const GROUPS = [
        'general' => 'General',
        'players' => 'Players',
        'world' => 'World',
        'performance' => 'Performance',
        'security' => 'Security',
    ];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * Whether an administrator left the Config Editor addon switched on.
     */
    public function addonEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING_ENABLED, '1');
    }

    /**
     * Property keys an administrator left switched on. Defaults to every key so
     * a fresh install behaves sensibly.
     */
    public function enabledFields(): array
    {
        $stored = $this->settings->get(self::SETTING_FIELDS);

        // Never configured yet -> everything is available.
        if ($stored === null || $stored === '') {
            return array_keys(self::FIELDS);
        }

        $decoded = json_decode($stored, true);

        // Corrupt value -> fail open rather than locking everyone out.
        if (!is_array($decoded)) {
            return array_keys(self::FIELDS);
        }

        // An explicitly saved empty list really does mean "nothing".
        // Reorder against the canonical list and drop anything unknown.
        return array_values(array_intersect(
            array_keys(self::FIELDS),
            array_map('strval', $decoded)
        ));
    }

    /**
     * Field definitions in the shape the client expects. Only the keys an
     * administrator enabled are handed out.
     */
    public function fields(): array
    {
        $fields = [];
        $enabled = $this->enabledFields();

        foreach (self::FIELDS as $key => $field) {
            if (!in_array($key, $enabled, true)) {
                continue;
            }

            $fields[] = array_merge([
                'key' => $key,
                'label' => $key,
                'description' => null,
                'group' => 'general',
                'type' => self::TYPE_TEXT,
                'options' => null,
                'min' => null,
                'max' => null,
                'warning' => false,
                'allow_custom' => false,
            ], $field);
        }

        return $fields;
    }

    /**
     * Turns the raw file into a key => value map, ignoring comments and any
     * line that is not a key=value pair.
     */
    public function parse(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
            $pair = $this->splitLine($line);

            if ($pair === null) {
                continue;
            }

            $values[$this->unescape(trim($pair[0]))] = $this->unescape(trim($pair[1]));
        }

        return $values;
    }

    /**
     * Only hands back the whitelisted keys, cast into the type the client uses.
     */
    public function readable(string $contents): array
    {
        $parsed = $this->parse($contents);
        $values = [];
        $enabled = $this->enabledFields();

        foreach (self::FIELDS as $key => $field) {
            if (!in_array($key, $enabled, true)) {
                continue;
            }

            if (!array_key_exists($key, $parsed)) {
                $values[$key] = null;

                continue;
            }

            $raw = $parsed[$key];

            $values[$key] = match ($field['type']) {
                self::TYPE_BOOLEAN => $raw === 'true',
                self::TYPE_NUMBER => is_numeric($raw) ? (int) $raw : null,
                self::TYPE_SELECT => $this->normaliseSelect($key, $raw),
                default => $raw,
            };
        }

        return $values;
    }

    /**
     * Writes the submitted values back into the original file, keeping comments,
     * ordering and unknown keys exactly as they were.
     */
    public function merge(string $contents, array $values): string
    {
        $replacements = [];
        $enabled = $this->enabledFields();

        foreach ($values as $key => $value) {
            // Disabled keys are ignored even when they are sent to the API.
            if (!array_key_exists($key, self::FIELDS) || !in_array($key, $enabled, true) || $value === null) {
                continue;
            }

            $replacements[$key] = $this->stringify($key, $value);
        }

        if (empty($replacements)) {
            return $contents;
        }

        $lines = preg_split('/\r\n|\r|\n/', $contents);
        $seen = [];

        foreach ($lines as $index => $line) {
            $pair = $this->splitLine($line);

            if ($pair === null) {
                continue;
            }

            $key = $this->unescape(trim($pair[0]));

            if (array_key_exists($key, $replacements)) {
                $lines[$index] = $key . '=' . $this->escape($replacements[$key]);
                $seen[$key] = true;
            }
        }

        // Keys the file never had yet simply get appended at the end.
        foreach ($replacements as $key => $value) {
            if (!isset($seen[$key])) {
                $lines[] = $key . '=' . $this->escape($value);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Validation rules generated straight from the whitelist.
     */
    public function rules(): array
    {
        $rules = ['values' => 'required|array'];
        $enabled = $this->enabledFields();

        foreach (self::FIELDS as $key => $field) {
            if (!in_array($key, $enabled, true)) {
                continue;
            }

            $rule = match ($field['type']) {
                self::TYPE_BOOLEAN => 'nullable|boolean',
                self::TYPE_NUMBER => sprintf(
                    'nullable|integer|min:%d|max:%d',
                    $field['min'] ?? 0,
                    $field['max'] ?? 1000000
                ),
                self::TYPE_SELECT => ($field['allow_custom'] ?? false)
                    ? 'nullable|string|max:191'
                    : 'nullable|string|in:' . implode(',', $field['options'] ?? []),
                default => 'nullable|string|max:' . ($field['max'] ?? 255),
            };

            $rules['values.' . $key] = $rule;
        }

        return $rules;
    }

    /**
     * Normalises everything the client submitted before it is validated, so a
     * legacy spelling coming straight back out of an old file is accepted
     * instead of failing the "in" rule.
     */
    public function normaliseInput(array $values): array
    {
        $normalised = [];

        foreach ($values as $key => $value) {
            $field = self::FIELDS[$key] ?? null;

            $normalised[$key] = $field !== null && $field['type'] === self::TYPE_SELECT
                ? $this->normaliseSelect((string) $key, $value)
                : $value;
        }

        return $normalised;
    }

    /**
     * Maps a raw select value onto the canonical option it means.
     *
     * Matching is case insensitive and understands the aliases above. A value
     * that cannot be matched is returned untouched: for a field marked
     * allow_custom that is a legitimate custom generator, and for every other
     * field the validator still rejects it with a readable message.
     */
    public function normaliseSelect(string $key, mixed $value): ?string
    {
        $field = self::FIELDS[$key] ?? null;

        if ($value === null || $field === null || $field['type'] !== self::TYPE_SELECT) {
            return $value === null ? null : (string) $value;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $alias = self::ALIASES[$key][strtolower($raw)] ?? null;

        if ($alias !== null) {
            return $alias;
        }

        foreach ($field['options'] ?? [] as $option) {
            if (strcasecmp($option, $raw) === 0) {
                return $option;
            }
        }

        return $raw;
    }

    /**
     * Splits a properties line into key and value.
     *
     * java.util.Properties treats the first unescaped "=" or ":" as the
     * separator, so a line like "level-type=minecraft\:normal" has to split on
     * the equals sign and keep the colon in the value.
     *
     * @return array{0: string, 1: string}|null null for blanks and comments
     */
    private function splitLine(string $line): ?array
    {
        $trimmed = trim($line);

        // "!" is a comment marker in the properties format as well.
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '!')) {
            return null;
        }

        $length = strlen($trimmed);

        for ($i = 0; $i < $length; $i++) {
            $character = $trimmed[$i];

            if ($character === '\\') {
                // Skip whatever the backslash escapes, including a separator.
                $i++;

                continue;
            }

            if ($character === '=' || $character === ':') {
                return [substr($trimmed, 0, $i), substr($trimmed, $i + 1)];
            }
        }

        return null;
    }

    /**
     * Removes the escaping java.util.Properties adds to separators.
     *
     * Unicode escapes such as \u00A7 are deliberately left alone: they are how
     * colour codes are stored in a MOTD, and rewriting them would either strip
     * the colours or double escape the backslash.
     */
    private function unescape(string $value): string
    {
        return preg_replace('/\\\\([:=#! ])/', '$1', $value) ?? $value;
    }

    /**
     * Escapes the separators again on the way back into the file, matching what
     * the game writes itself. Backslashes are left as they are so an existing
     * \u00A7 colour code round trips unchanged.
     */
    private function escape(string $value): string
    {
        return str_replace([':', '='], ['\\:', '\\='], $value);
    }

    /**
     * Converts a submitted value into the literal that goes into the file. Line
     * breaks are stripped so a single value can never inject extra keys.
     */
    private function stringify(string $key, mixed $value): string
    {
        $field = self::FIELDS[$key];

        if ($field['type'] === self::TYPE_BOOLEAN) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        if ($field['type'] === self::TYPE_NUMBER) {
            $number = (int) $value;
            $number = max($field['min'] ?? PHP_INT_MIN, min($field['max'] ?? PHP_INT_MAX, $number));

            return (string) $number;
        }

        return trim(str_replace(["\r", "\n"], '', (string) $value));
    }
}
