<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // button.idbutton / game.idgame are `bigint unsigned` on production
        // but legacy signed `int` on local WAMP snapshots; match whichever
        // this environment actually has so the FK constraints type-check
        // either way.
        $buttonIdIsLegacyInt = Schema::getColumnType('button', 'idbutton') === 'int';
        $gameIdIsLegacyInt = Schema::getColumnType('game', 'idgame') === 'int';

        Schema::create('button_alias', function (Blueprint $table) use ($buttonIdIsLegacyInt, $gameIdIsLegacyInt) {
            $table->id('idbuttonalias');
            $table->string('alias', 45);

            if ($buttonIdIsLegacyInt) {
                $table->integer('button_idbutton');
                $table->foreign('button_idbutton')->references('idbutton')->on('button')->cascadeOnDelete();
            } else {
                $table->foreignId('button_idbutton')->constrained('button', 'idbutton')->cascadeOnDelete();
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
        Schema::dropIfExists('button_alias');
    }
};
