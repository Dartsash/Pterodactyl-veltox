<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('addon_states', function (Blueprint $table) {
            $table->increments('id');
            $table->string('addon_id')->unique();
            $table->boolean('enabled')->default(true);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('addon_states');
    }
};
