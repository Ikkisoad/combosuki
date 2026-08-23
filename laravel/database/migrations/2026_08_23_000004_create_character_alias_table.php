<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // character.idcharacter / game.idgame are `bigint unsigned` on
        // production but legacy signed `int` on local WAMP snapshots; match
        // whichever this environment actually has so the FK constraints
        // type-check either way.
        $characterIdIsLegacyInt = Schema::getColumnType('character', 'idcharacter') === 'int';
        $gameIdIsLegacyInt = Schema::getColumnType('game', 'idgame') === 'int';

        Schema::create('character_alias', function (Blueprint $table) use ($characterIdIsLegacyInt, $gameIdIsLegacyInt) {
            $table->id('idcharacteralias');
            $table->string('alias', 50);

            if ($characterIdIsLegacyInt) {
                $table->integer('character_idcharacter');
                $table->foreign('character_idcharacter')->references('idcharacter')->on('character')->cascadeOnDelete();
            } else {
                $table->foreignId('character_idcharacter')->constrained('character', 'idcharacter')->cascadeOnDelete();
            }

            if ($gameIdIsLegacyInt) {
                $table->integer('game_idgame');
                $table->foreign('game_idgame')->references('idgame')->on('game')->cascadeOnDelete();
            } else {
                $table->foreignId('game_idgame')->constrained('game', 'idgame')->cascadeOnDelete();
            }

            $table->timestamps();

            $table->unique(['game_idgame', 'alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_alias');
    }
};
