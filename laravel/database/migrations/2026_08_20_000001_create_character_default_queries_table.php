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
            // Plain `integer`, not foreignId()'s unsignedBigInteger: the live
            // `game.idgame` column is a signed `int` (legacy schema), so the
            // FK column type has to match it exactly for MySQL to accept the
            // constraint (see the other game_idgame/*_iduser columns across
            // this schema, which are all plain int for the same reason).
            $table->integer('game_idgame');
            $table->string('label', 150);
            $table->json('filters');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('game_idgame')->references('idgame')->on('game')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_default_queries');
    }
};
