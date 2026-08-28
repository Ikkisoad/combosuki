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
        // actually has so the FK constraint type-checks either way (see
        // character_resource_value_alias migration for the same pattern).
        $gameIdIsLegacyInt = Schema::getColumnType('game', 'idgame') === 'int';

        Schema::create('game_patches', function (Blueprint $table) use ($gameIdIsLegacyInt) {
            $table->id('idgame_patch');

            if ($gameIdIsLegacyInt) {
                $table->integer('game_idgame');
                $table->foreign('game_idgame')->references('idgame')->on('game')->cascadeOnDelete();
            } else {
                $table->foreignId('game_idgame')->constrained('game', 'idgame')->cascadeOnDelete();
            }

            $table->string('label', 10);
            $table->date('released_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::table('combo', function (Blueprint $table) {
            $table->foreignId('patch_idgame_patch')->nullable()->after('patch')
                ->constrained('game_patches', 'idgame_patch')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('combo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patch_idgame_patch');
        });

        Schema::dropIfExists('game_patches');
    }
};
