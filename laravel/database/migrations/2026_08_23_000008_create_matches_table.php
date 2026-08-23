<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // game.idgame, character.idcharacter and user.iduser are `bigint
        // unsigned` on production but legacy signed `int` on local WAMP
        // snapshots; match whichever this environment actually has so each
        // FK constraint type-checks either way.
        $gameIdIsLegacyInt = Schema::getColumnType('game', 'idgame') === 'int';
        $characterIdIsLegacyInt = Schema::getColumnType('character', 'idcharacter') === 'int';
        $userIdIsLegacyInt = Schema::getColumnType('user', 'iduser') === 'int';

        Schema::create('matches', function (Blueprint $table) use ($gameIdIsLegacyInt, $characterIdIsLegacyInt, $userIdIsLegacyInt) {
            $table->id('idmatch');

            if ($gameIdIsLegacyInt) {
                $table->integer('game_idgame');
                $table->foreign('game_idgame')->references('idgame')->on('game')->cascadeOnDelete();
            } else {
                $table->foreignId('game_idgame')->constrained('game', 'idgame')->cascadeOnDelete();
            }

            $table->string('player_one', 100);

            if ($userIdIsLegacyInt) {
                $table->integer('player_one_user_iduser')->nullable();
                $table->foreign('player_one_user_iduser')->references('iduser')->on('user')->nullOnDelete();
            } else {
                $table->foreignId('player_one_user_iduser')->nullable()->constrained('user', 'iduser')->nullOnDelete();
            }

            if ($characterIdIsLegacyInt) {
                $table->integer('player_one_character_idcharacter');
                $table->foreign('player_one_character_idcharacter')->references('idcharacter')->on('character')->cascadeOnDelete();
            } else {
                $table->foreignId('player_one_character_idcharacter')->constrained('character', 'idcharacter')->cascadeOnDelete();
            }

            $table->string('player_two', 100);

            if ($userIdIsLegacyInt) {
                $table->integer('player_two_user_iduser')->nullable();
                $table->foreign('player_two_user_iduser')->references('iduser')->on('user')->nullOnDelete();
            } else {
                $table->foreignId('player_two_user_iduser')->nullable()->constrained('user', 'iduser')->nullOnDelete();
            }

            if ($characterIdIsLegacyInt) {
                $table->integer('player_two_character_idcharacter');
                $table->foreign('player_two_character_idcharacter')->references('idcharacter')->on('character')->cascadeOnDelete();
            } else {
                $table->foreignId('player_two_character_idcharacter')->constrained('character', 'idcharacter')->cascadeOnDelete();
            }

            $table->mediumText('video');
            $table->date('played_at');

            if ($userIdIsLegacyInt) {
                $table->integer('user_iduser')->nullable();
                $table->foreign('user_iduser')->references('iduser')->on('user')->nullOnDelete();
            } else {
                $table->foreignId('user_iduser')->nullable()->constrained('user', 'iduser')->nullOnDelete();
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
