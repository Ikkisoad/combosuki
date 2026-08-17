<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources_values', function (Blueprint $table) {
            $table->id('idResources_values');
            $table->string('value', 115);
            $table->integer('order')->nullable();
            $table->foreignId('game_resources_idgame_resources')
                ->constrained('game_resources', 'idgame_resources')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources_values');
    }
};
