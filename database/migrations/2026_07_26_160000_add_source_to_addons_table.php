<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teaches the Plugin Manager where an addon's releases live, which is what the
 * "Available versions" picker in the marketplace reads.
 *
 *  - source     : static | modrinth | github | hangar
 *  - source_id  : project slug (modrinth), owner/repo (github), Author/Project (hangar)
 *
 * `static` keeps the old behaviour: exactly one download URL, no version list.
 * `url` becomes nullable because API backed addons resolve their jar at install
 * time instead of storing a link that goes stale after every release.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            if (!Schema::hasColumn('addons', 'source')) {
                $table->string('source')->default('static')->after('url');
            }

            if (!Schema::hasColumn('addons', 'source_id')) {
                $table->string('source_id')->nullable()->after('source');
            }
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->text('url')->nullable()->change();
        });

        foreach ($this->sources() as $slug => $source) {
            DB::table('addons')->where('slug', $slug)->update([
                'source' => $source[0],
                'source_id' => $source[1],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            if (Schema::hasColumn('addons', 'source')) {
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('addons', 'source_id')) {
                $table->dropColumn('source_id');
            }
        });
    }

    /**
     * Release sources for the addons that already exist in the catalog, so they
     * get a version list too instead of being stuck on one hardcoded link.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function sources(): array
    {
        return [
            'essentialsx' => ['github', 'EssentialsX/Essentials'],
            'essentialsx-chat' => ['github', 'EssentialsX/Essentials'],
            'essentialsx-spawn' => ['github', 'EssentialsX/Essentials'],
            'vault' => ['github', 'MilkBowl/Vault'],
            'protocollib' => ['github', 'dmulloy2/ProtocolLib'],
            'placeholderapi' => ['github', 'PlaceholderAPI/PlaceholderAPI'],
            'viaversion' => ['github', 'ViaVersion/ViaVersion'],
            'viabackwards' => ['github', 'ViaVersion/ViaBackwards'],
            'griefprevention' => ['github', 'GriefPrevention/GriefPrevention'],
            'coreprotect' => ['github', 'PlayPro/CoreProtect'],
            'multiverse-core' => ['github', 'Multiverse/Multiverse-Core'],
            'worldedit' => ['modrinth', 'worldedit'],
            'worldguard' => ['modrinth', 'worldguard'],
            'luckperms' => ['modrinth', 'luckperms'],
            'chunky' => ['modrinth', 'chunky'],
            'bluemap' => ['modrinth', 'bluemap'],
            'spark' => ['modrinth', 'spark'],
            'dynmap' => ['modrinth', 'dynmap'],
            'fabric-api' => ['modrinth', 'fabric-api'],
            'lithium' => ['modrinth', 'lithium'],
            'simple-voice-chat' => ['modrinth', 'simple-voice-chat'],
            'terralith' => ['modrinth', 'terralith'],
            // Geyser and Floodgate already point at an "always latest" endpoint
            // that never goes stale, so they stay static on purpose.
        ];
    }
};
