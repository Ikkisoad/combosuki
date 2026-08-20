<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_default_queries', function (Blueprint $table) {
            $table->id('idquery');
            $table->foreignId('game_idgame')->constrained('game', 'idgame')->cascadeOnDelete();
            $table->string('label', 150);
            $table->json('filters');
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_default_queries');
    }
};
