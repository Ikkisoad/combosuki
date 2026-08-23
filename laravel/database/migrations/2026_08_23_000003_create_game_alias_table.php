<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // game.idgame is `bigint unsigned` on production but a legacy signed
        // `int` on local WAMP snapshots; match whichever this environment
        // actually has so the FK constraint type-checks either way.
        $gameIdIsLegacyInt = Schema::getColumnType('game', 'idgame') === 'int';

        Schema::create('game_alias', function (Blueprint $table) use ($gameIdIsLegacyInt) {
            $table->id('idgamealias');
            $table->string('alias', 50);

            if ($gameIdIsLegacyInt) {
                $table->integer('game_idgame');
                $table->foreign('game_idgame')->references('idgame')->on('game')->cascadeOnDelete();
            } else {
                $table->foreignId('game_idgame')->constrained('game', 'idgame')->cascadeOnDelete();
            }

            $table->timestamps();

            $table->unique('alias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_alias');
    }
};
