<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // matches.idmatch, game_resources.idgame_resources and
        // resources_values.idResources_values are `bigint unsigned` on
        // production but legacy signed `int` on local WAMP snapshots; match
        // whichever this environment actually has so each FK constraint
        // type-checks either way (see character_game_resources migration for
        // the same pattern).
        $matchIdIsLegacyInt = Schema::getColumnType('matches', 'idmatch') === 'int';
        $gameResourceIdIsLegacyInt = Schema::getColumnType('game_resources', 'idgame_resources') === 'int';
        $resourceValueIdIsLegacyInt = Schema::getColumnType('resources_values', 'idResources_values') === 'int';

        Schema::create('match_resources', function (Blueprint $table) use ($matchIdIsLegacyInt, $gameResourceIdIsLegacyInt, $resourceValueIdIsLegacyInt) {
            $table->id('idmatch_resources');

            if ($matchIdIsLegacyInt) {
                $table->integer('match_idmatch');
                $table->foreign('match_idmatch')->references('idmatch')->on('matches')->cascadeOnDelete();
            } else {
                $table->foreignId('match_idmatch')->constrained('matches', 'idmatch')->cascadeOnDelete();
            }

            if ($gameResourceIdIsLegacyInt) {
                $table->integer('game_resources_idgame_resources');
                $table->foreign('game_resources_idgame_resources')->references('idgame_resources')->on('game_resources')->cascadeOnDelete();
            } else {
                $table->foreignId('game_resources_idgame_resources')->constrained('game_resources', 'idgame_resources')->cascadeOnDelete();
            }

            if ($resourceValueIdIsLegacyInt) {
                $table->integer('resources_values_idResources_values');
                $table->foreign('resources_values_idResources_values')->references('idResources_values')->on('resources_values')->cascadeOnDelete();
            } else {
                $table->foreignId('resources_values_idResources_values')->constrained('resources_values', 'idResources_values')->cascadeOnDelete();
            }

            $table->unsignedTinyInteger('player');

            $table->timestamps();

            $table->unique(['match_idmatch', 'player', 'game_resources_idgame_resources'], 'match_resources_unique_player_resource');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_resources');
    }
};
