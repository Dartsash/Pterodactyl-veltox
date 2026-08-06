<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a larger catalog of popular plugins into the `addons` table.
 *
 * This migration is IDEMPOTENT and SAFE to run on an existing database:
 *  - Rows are matched by their unique `slug`.
 *  - If a slug already exists, its metadata + download URL are refreshed,
 *    but its current `enabled` state is left untouched.
 *  - If a slug is new, it is inserted (enabled = true).
 *
 * All download URLs are direct .jar links (or official redirects that always
 * resolve to the latest build). Wings downloads the file server-side, so these
 * must be reachable without a browser / login.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->plugins() as $p) {
            $existing = DB::table('addons')->where('slug', $p['slug'])->first();

            $data = $p;
            $data['updated_at'] = $now;

            if ($existing) {
                // Keep the admin's current enabled/disabled choice.
                DB::table('addons')->where('slug', $p['slug'])->update($data);
            } else {
                $data['enabled'] = true;
                $data['created_at'] = $now;
                DB::table('addons')->insert($data);
            }
        }
    }

    public function down(): void
    {
        // Only remove the plugins this migration introduced.
        $slugs = array_column($this->newSlugs(), 0);
        DB::table('addons')->whereIn('slug', $slugs)->delete();
    }

    /**
     * Slugs that are newly introduced by this migration (used by down()).
     */
    private function newSlugs(): array
    {
        return [
            ['essentialsx-chat'], ['essentialsx-spawn'], ['protocollib'],
            ['placeholderapi'], ['viabackwards'], ['geyser'], ['floodgate'],
            ['griefprevention'], ['worldedit'], ['worldguard'], ['coreprotect'],
            ['multiverse-core'],
        ];
    }

    private function plugins(): array
    {
        return [
            // ---- Core / permissions / economy ----
            [
                'slug' => 'essentialsx',
                'name' => 'EssentialsX',
                'author' => 'EssentialsX Team',
                'category' => 'Plugin',
                'version' => '2.20.1',
                'url' => 'https://github.com/EssentialsX/Essentials/releases/download/2.20.1/EssentialsX-2.20.1.jar',
                'filename' => 'EssentialsX.jar',
                'downloads' => '90M+',
                'rating' => 4.8,
                'description' => 'The essential toolkit for Spigot/Paper servers: homes, warps, kits, economy, moderation and hundreds of commands.',
            ],
            [
                'slug' => 'essentialsx-chat',
                'name' => 'EssentialsX Chat',
                'author' => 'EssentialsX Team',
                'category' => 'Plugin',
                'version' => '2.20.1',
                'url' => 'https://github.com/EssentialsX/Essentials/releases/download/2.20.1/EssentialsXChat-2.20.1.jar',
                'filename' => 'EssentialsXChat.jar',
                'downloads' => '—',
                'rating' => 4.7,
                'description' => 'Chat formatting, prefixes and channels. Requires EssentialsX.',
            ],
            [
                'slug' => 'essentialsx-spawn',
                'name' => 'EssentialsX Spawn',
                'author' => 'EssentialsX Team',
                'category' => 'Plugin',
                'version' => '2.20.1',
                'url' => 'https://github.com/EssentialsX/Essentials/releases/download/2.20.1/EssentialsXSpawn-2.20.1.jar',
                'filename' => 'EssentialsXSpawn.jar',
                'downloads' => '—',
                'rating' => 4.7,
                'description' => 'Adds /spawn and first-join spawn control. Requires EssentialsX.',
            ],
            [
                'slug' => 'vault',
                'name' => 'Vault',
                'author' => 'MilkBowl',
                'category' => 'Plugin',
                'version' => '1.7.3',
                'url' => 'https://github.com/MilkBowl/Vault/releases/latest/download/Vault.jar',
                'filename' => 'Vault.jar',
                'downloads' => '80M+',
                'rating' => 4.8,
                'description' => 'Abstraction library for economy, permissions and chat. A dependency for many plugins.',
            ],
            [
                'slug' => 'luckperms',
                'name' => 'LuckPerms',
                'author' => 'Luck',
                'category' => 'Plugin',
                'version' => '5.4.145',
                'url' => 'https://download.luckperms.net/1556/bukkit/loader/LuckPerms-Bukkit-5.4.145.jar',
                'filename' => 'LuckPerms.jar',
                'downloads' => '50M+',
                'rating' => 4.9,
                'description' => 'The most powerful and flexible permissions plugin, with a web editor.',
            ],
            [
                'slug' => 'placeholderapi',
                'name' => 'PlaceholderAPI',
                'author' => 'HelpChat',
                'category' => 'Plugin',
                'version' => '2.11.6',
                'url' => 'https://github.com/PlaceholderAPI/PlaceholderAPI/releases/download/2.11.6/PlaceholderAPI-2.11.6.jar',
                'filename' => 'PlaceholderAPI.jar',
                'downloads' => '40M+',
                'rating' => 4.9,
                'description' => 'Adds dynamic placeholders used by hundreds of other plugins.',
            ],
            [
                'slug' => 'protocollib',
                'name' => 'ProtocolLib',
                'author' => 'dmulloy2',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => 'https://github.com/dmulloy2/ProtocolLib/releases/latest/download/ProtocolLib.jar',
                'filename' => 'ProtocolLib.jar',
                'downloads' => '40M+',
                'rating' => 4.8,
                'description' => 'Read and modify the Minecraft protocol. Required by many plugins.',
            ],

            // ---- Version support ----
            [
                'slug' => 'viaversion',
                'name' => 'ViaVersion',
                'author' => 'ViaVersion',
                'category' => 'Plugin',
                'version' => '5.1.1',
                'url' => 'https://github.com/ViaVersion/ViaVersion/releases/download/5.1.1/ViaVersion-5.1.1.jar',
                'filename' => 'ViaVersion.jar',
                'downloads' => '30M+',
                'rating' => 4.8,
                'description' => 'Allow newer Minecraft clients to join older servers.',
            ],
            [
                'slug' => 'viabackwards',
                'name' => 'ViaBackwards',
                'author' => 'ViaVersion',
                'category' => 'Plugin',
                'version' => '5.1.1',
                'url' => 'https://github.com/ViaVersion/ViaBackwards/releases/download/5.1.1/ViaBackwards-5.1.1.jar',
                'filename' => 'ViaBackwards.jar',
                'downloads' => '20M+',
                'rating' => 4.7,
                'description' => 'Allow older Minecraft clients to join newer servers. Requires ViaVersion.',
            ],

            // ---- Bedrock crossplay ----
            [
                'slug' => 'geyser',
                'name' => 'Geyser',
                'author' => 'GeyserMC',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => 'https://download.geysermc.org/v2/projects/geyser/versions/latest/builds/latest/downloads/spigot',
                'filename' => 'Geyser-Spigot.jar',
                'downloads' => 'latest build',
                'rating' => 4.9,
                'description' => 'Lets Bedrock Edition players join your Java Edition server.',
            ],
            [
                'slug' => 'floodgate',
                'name' => 'Floodgate',
                'author' => 'GeyserMC',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => 'https://download.geysermc.org/v2/projects/floodgate/versions/latest/builds/latest/downloads/spigot',
                'filename' => 'floodgate-spigot.jar',
                'downloads' => 'latest build',
                'rating' => 4.8,
                'description' => 'Companion to Geyser: lets Bedrock players join without a Java account.',
            ],

            // ---- Protection / world editing ----
            [
                'slug' => 'griefprevention',
                'name' => 'GriefPrevention',
                'author' => 'TechFortress',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => 'https://github.com/GriefPrevention/GriefPrevention/releases/latest/download/GriefPrevention.jar',
                'filename' => 'GriefPrevention.jar',
                'downloads' => '10M+',
                'rating' => 4.8,
                'description' => 'Self-service anti-grief land claims with a golden shovel.',
            ],
            [
                'slug' => 'worldedit',
                'name' => 'WorldEdit',
                'author' => 'EngineHub',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => 'https://dev.bukkit.org/projects/worldedit/files/latest',
                'filename' => 'WorldEdit.jar',
                'downloads' => '100M+',
                'rating' => 4.9,
                'description' => 'Fast in-game map editor: build, copy, paste and terraform instantly.',
            ],
            [
                'slug' => 'worldguard',
                'name' => 'WorldGuard',
                'author' => 'EngineHub',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => 'https://dev.bukkit.org/projects/worldguard/files/latest',
                'filename' => 'WorldGuard.jar',
                'downloads' => '80M+',
                'rating' => 4.8,
                'description' => 'Region protection, flags and anti-grief. Requires WorldEdit.',
            ],
            [
                'slug' => 'coreprotect',
                'name' => 'CoreProtect',
                'author' => 'Intelli',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => 'https://dev.bukkit.org/projects/coreprotect/files/latest',
                'filename' => 'CoreProtect.jar',
                'downloads' => '30M+',
                'rating' => 4.9,
                'description' => 'Fast block logging and rollback tool to undo grief.',
            ],

            // ---- Utility ----
            [
                'slug' => 'multiverse-core',
                'name' => 'Multiverse-Core',
                'author' => 'Multiverse',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => 'https://dev.bukkit.org/projects/multiverse-core/files/latest',
                'filename' => 'Multiverse-Core.jar',
                'downloads' => '20M+',
                'rating' => 4.7,
                'description' => 'Manage multiple worlds with per-world settings and portals.',
            ],
        ];
    }
};
