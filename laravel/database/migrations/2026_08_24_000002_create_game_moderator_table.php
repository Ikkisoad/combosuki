<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // game.idgame and user.iduser are `bigint unsigned` on production
        // but legacy signed `int` on local WAMP snapshots; match whichever
        // this environment actually has so each FK constraint type-checks
        // either way (see matches table migration for the same pattern).
        $gameIdIsLegacyInt = Schema::getColumnType('game', 'idgame') === 'int';
        $userIdIsLegacyInt = Schema::getColumnType('user', 'iduser') === 'int';

        Schema::create('game_moderator', function (Blueprint $table) use ($gameIdIsLegacyInt, $userIdIsLegacyInt) {
            if ($gameIdIsLegacyInt) {
                $table->integer('idgame');
                $table->foreign('idgame')->references('idgame')->on('game')->cascadeOnDelete();
            } else {
                $table->foreignId('idgame')->constrained('game', 'idgame')->cascadeOnDelete();
            }

            if ($userIdIsLegacyInt) {
                $table->integer('iduser');
                $table->foreign('iduser')->references('iduser')->on('user')->cascadeOnDelete();
            } else {
                $table->foreignId('iduser')->constrained('user', 'iduser')->cascadeOnDelete();
            }

            $table->timestamps();

            $table->primary(['idgame', 'iduser']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_moderator');
    }
};
