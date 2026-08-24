<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // character.idcharacter and game_resources.idgame_resources are
        // `bigint unsigned` on production but legacy signed `int` on local
        // WAMP snapshots; match whichever this environment actually has so
        // each FK constraint type-checks either way (see game_moderator
        // migration for the same pattern).
        $characterIdIsLegacyInt = Schema::getColumnType('character', 'idcharacter') === 'int';
        $gameResourceIdIsLegacyInt = Schema::getColumnType('game_resources', 'idgame_resources') === 'int';

        Schema::create('character_game_resources', function (Blueprint $table) use ($characterIdIsLegacyInt, $gameResourceIdIsLegacyInt) {
            if ($characterIdIsLegacyInt) {
                $table->integer('character_idcharacter');
                $table->foreign('character_idcharacter')->references('idcharacter')->on('character')->cascadeOnDelete();
            } else {
                $table->foreignId('character_idcharacter')->constrained('character', 'idcharacter')->cascadeOnDelete();
            }

            if ($gameResourceIdIsLegacyInt) {
                $table->integer('game_resources_idgame_resources');
                $table->foreign('game_resources_idgame_resources')->references('idgame_resources')->on('game_resources')->cascadeOnDelete();
            } else {
                $table->foreignId('game_resources_idgame_resources')->constrained('game_resources', 'idgame_resources')->cascadeOnDelete();
            }

            $table->timestamps();

            $table->primary(['character_idcharacter', 'game_resources_idgame_resources']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_game_resources');
    }
};
