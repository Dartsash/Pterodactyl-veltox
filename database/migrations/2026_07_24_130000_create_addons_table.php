<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('author')->nullable();
            $table->string('category')->default('Plugin');
            $table->text('description')->nullable();
            $table->string('version')->nullable();
            $table->text('url');
            $table->string('filename');
            $table->string('downloads')->nullable();
            $table->decimal('rating', 3, 1)->default(5.0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        $now = now();
        $seed = [
            ['slug' => 'vault', 'name' => 'Vault', 'author' => 'MilkBowl', 'category' => 'Plugin', 'version' => '1.7.3', 'url' => 'https://github.com/MilkBowl/Vault/releases/download/1.7.3/Vault.jar', 'filename' => 'Vault.jar', 'downloads' => '51.5M', 'rating' => 4.8, 'description' => 'Abstraction layer for economy, permissions and chat used by most plugins.'],
            ['slug' => 'essentialsx', 'name' => 'EssentialsX', 'author' => 'EssentialsX Team', 'category' => 'Plugin', 'version' => '2.20.1', 'url' => 'https://github.com/EssentialsX/Essentials/releases/download/2.20.1/EssentialsX-2.20.1.jar', 'filename' => 'EssentialsX.jar', 'downloads' => '91.7M', 'rating' => 4.8, 'description' => 'The essential toolkit: homes, warps, kits, economy and hundreds of commands.'],
            ['slug' => 'viaversion', 'name' => 'ViaVersion', 'author' => 'ViaVersion', 'category' => 'Plugin', 'version' => '5.1.1', 'url' => 'https://github.com/ViaVersion/ViaVersion/releases/download/5.1.1/ViaVersion-5.1.1.jar', 'filename' => 'ViaVersion.jar', 'downloads' => '44.3M', 'rating' => 4.8, 'description' => 'Let newer clients join older servers. Keep one version, support many.'],
            ['slug' => 'luckperms', 'name' => 'LuckPerms', 'author' => 'Luck', 'category' => 'Plugin', 'version' => '5.4.145', 'url' => 'https://download.luckperms.net/1556/bukkit/loader/LuckPerms-Bukkit-5.4.145.jar', 'filename' => 'LuckPerms.jar', 'downloads' => '46.2M', 'rating' => 4.9, 'description' => 'A powerful, flexible permissions plugin with per-group and per-user control.'],
        ];
        foreach ($seed as $row) {
            $row['enabled'] = true;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            DB::table('addons')->insert($row);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
