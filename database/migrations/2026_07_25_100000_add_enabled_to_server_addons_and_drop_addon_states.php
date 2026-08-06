<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Per-server enable/disable state for an installed addon. Disabling
        // renames the jar to <file>.disabled on the node instead of deleting it.
        Schema::table('server_addons', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('version');
        });

        // `addon_states` never had a single code path reading or writing it and
        // duplicated `addons.enabled`, which is what actually gates the
        // marketplace. Dropping it removes the ambiguity.
        Schema::dropIfExists('addon_states');
    }

    public function down(): void
    {
        Schema::table('server_addons', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });

        Schema::create('addon_states', function (Blueprint $table) {
            $table->increments('id');
            $table->string('addon_id')->unique();
            $table->boolean('enabled')->default(true);
        });
    }
};
