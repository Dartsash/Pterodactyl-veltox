<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Second catalog expansion for the Plugin Manager.
 *
 * IDEMPOTENT: rows are matched by their unique `slug`. Existing rows get their
 * metadata and release source refreshed, but their enabled/disabled state is
 * left alone. New rows are inserted enabled.
 *
 * Every entry declares a release source, so the marketplace can offer a list of
 * available versions instead of a single hardcoded link:
 *  - modrinth : `source_id` is the Modrinth project slug
 *  - github   : `source_id` is owner/repo, the newest matching .jar asset wins
 *  - static   : `url` is used as-is (only for "always latest" endpoints)
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->catalog() as $addon) {
            $addon['updated_at'] = $now;

            $exists = DB::table('addons')->where('slug', $addon['slug'])->exists();

            if ($exists) {
                DB::table('addons')->where('slug', $addon['slug'])->update($addon);

                continue;
            }

            $addon['enabled'] = true;
            $addon['created_at'] = $now;

            DB::table('addons')->insert($addon);
        }
    }

    public function down(): void
    {
        DB::table('addons')->whereIn('slug', array_column($this->catalog(), 'slug'))->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            // ---------------------------------------------------- performance
            [
                'slug' => 'spark',
                'name' => 'spark',
                'author' => 'lucko',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'spark',
                'filename' => 'spark.jar',
                'downloads' => '15M+',
                'rating' => 4.9,
                'description' => 'Performance profiler: find lag spikes, tick stalls and memory leaks from in-game.',
            ],
            [
                'slug' => 'chunky',
                'name' => 'Chunky',
                'author' => 'pop4959',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'chunky',
                'filename' => 'Chunky.jar',
                'downloads' => '12M+',
                'rating' => 4.8,
                'description' => 'Pre-generates your world in the background so exploring players stop causing lag.',
            ],

            // ---------------------------------------------------- world maps
            [
                'slug' => 'bluemap',
                'name' => 'BlueMap',
                'author' => 'Blue (TBlueF)',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'bluemap',
                'filename' => 'BlueMap.jar',
                'downloads' => '7M+',
                'rating' => 4.8,
                'description' => '3D web map of your world you can fly through in a browser.',
            ],
            [
                'slug' => 'squaremap',
                'name' => 'squaremap',
                'author' => 'jpenilla',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'squaremap',
                'filename' => 'squaremap.jar',
                'downloads' => '3M+',
                'rating' => 4.8,
                'description' => 'Lightweight, minimalistic top-down live web map. Very cheap on performance.',
            ],
            [
                'slug' => 'dynmap',
                'name' => 'Dynmap',
                'author' => 'webbukkit',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'dynmap',
                'filename' => 'Dynmap.jar',
                'downloads' => '22M+',
                'rating' => 4.6,
                'description' => 'Google-Maps-style live web map of your world, updated in real time.',
            ],

            // ---------------------------------------------------- building
            [
                'slug' => 'fastasyncworldedit',
                'name' => 'FastAsyncWorldEdit',
                'author' => 'IntellectualSites',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'github',
                'source_id' => 'IntellectualSites/FastAsyncWorldEdit',
                'filename' => 'FastAsyncWorldEdit.jar',
                'downloads' => '20M+',
                'rating' => 4.8,
                'description' => 'Drop-in WorldEdit replacement with async, multi-threaded edits. Do not use together with WorldEdit.',
            ],
            [
                'slug' => 'plotsquared',
                'name' => 'PlotSquared',
                'author' => 'IntellectualSites',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'github',
                'source_id' => 'IntellectualSites/PlotSquared',
                'filename' => 'PlotSquared.jar',
                'downloads' => '10M+',
                'rating' => 4.7,
                'description' => 'The plot management plugin for creative servers: claim, merge and manage plots.',
            ],

            // ---------------------------------------------------- community
            [
                'slug' => 'discordsrv',
                'name' => 'DiscordSRV',
                'author' => 'Scarsz',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'github',
                'source_id' => 'DiscordSRV/DiscordSRV',
                'filename' => 'DiscordSRV.jar',
                'downloads' => '9M+',
                'rating' => 4.7,
                'description' => 'Two-way chat bridge between your server and a Discord channel, plus account linking.',
            ],
            [
                'slug' => 'plan',
                'name' => 'Plan (Player Analytics)',
                'author' => 'AuroraLS3',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'github',
                'source_id' => 'plan-player-analytics/Plan',
                'filename' => 'Plan.jar',
                'downloads' => '4M+',
                'rating' => 4.8,
                'description' => 'Web dashboard with player retention, playtime and activity statistics.',
            ],
            [
                'slug' => 'huskhomes',
                'name' => 'HuskHomes',
                'author' => 'William278',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'huskhomes',
                'filename' => 'HuskHomes.jar',
                'downloads' => '1M+',
                'rating' => 4.8,
                'description' => 'Modern homes, warps and teleport requests with cross-server support.',
            ],

            // ---------------------------------------------------- security
            [
                'slug' => 'authme',
                'name' => 'AuthMeReloaded',
                'author' => 'AuthMe Team',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'github',
                'source_id' => 'AuthMe/AuthMeReloaded',
                'filename' => 'AuthMe.jar',
                'downloads' => '8M+',
                'rating' => 4.6,
                'description' => 'Login and registration for offline-mode servers, with session and IP protection.',
            ],
            [
                'slug' => 'viarewind',
                'name' => 'ViaRewind',
                'author' => 'ViaVersion',
                'category' => 'Plugin',
                'version' => 'latest',
                'url' => null,
                'source' => 'github',
                'source_id' => 'ViaVersion/ViaRewind',
                'filename' => 'ViaRewind.jar',
                'downloads' => '5M+',
                'rating' => 4.6,
                'description' => 'Lets 1.8 and 1.7 clients join modern servers. Requires ViaVersion and ViaBackwards.',
            ],

            // ---------------------------------------------------- mods
            [
                'slug' => 'fabric-api',
                'name' => 'Fabric API',
                'author' => 'FabricMC',
                'category' => 'Mod',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'fabric-api',
                'filename' => 'fabric-api.jar',
                'downloads' => '120M+',
                'rating' => 4.9,
                'description' => 'Core hooks required by almost every Fabric mod.',
            ],
            [
                'slug' => 'lithium',
                'name' => 'Lithium',
                'author' => 'CaffeineMC',
                'category' => 'Mod',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'lithium',
                'filename' => 'lithium.jar',
                'downloads' => '35M+',
                'rating' => 4.9,
                'description' => 'General-purpose optimization mod that boosts server tick performance.',
            ],
            [
                'slug' => 'ferrite-core',
                'name' => 'FerriteCore',
                'author' => 'malte0811',
                'category' => 'Mod',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'ferrite-core',
                'filename' => 'ferritecore.jar',
                'downloads' => '30M+',
                'rating' => 4.8,
                'description' => 'Cuts memory usage significantly, especially on heavily modded servers.',
            ],
            [
                'slug' => 'simple-voice-chat',
                'name' => 'Simple Voice Chat',
                'author' => 'henkelmax',
                'category' => 'Mod',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'simple-voice-chat',
                'filename' => 'voicechat.jar',
                'downloads' => '29M+',
                'rating' => 4.9,
                'description' => 'Proximity voice chat with a configurable UI. Works on Fabric, NeoForge and Paper.',
            ],

            // ---------------------------------------------------- datapacks
            [
                'slug' => 'terralith',
                'name' => 'Terralith',
                'author' => 'Stardust Labs',
                'category' => 'Datapack',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'terralith',
                'filename' => 'Terralith.zip',
                'downloads' => '9M+',
                'rating' => 4.9,
                'description' => 'Overhauls world generation with 100+ new biomes using only vanilla blocks.',
            ],
            [
                'slug' => 'explorify',
                'name' => 'Explorify',
                'author' => 'Starmute',
                'category' => 'Datapack',
                'version' => 'latest',
                'url' => null,
                'source' => 'modrinth',
                'source_id' => 'explorify',
                'filename' => 'Explorify.zip',
                'downloads' => '2M+',
                'rating' => 4.8,
                'description' => 'Adds new vanilla-styled structures to explore without touching world generation.',
            ],
        ];
    }
};
