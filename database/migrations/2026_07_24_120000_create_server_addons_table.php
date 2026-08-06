<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('server_addons', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->string('addon_id');
            $table->string('version');
            $table->timestamp('installed_at')->useCurrent();
            $table->unique(['server_id', 'addon_id']);
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('server_addons');
    }
};
