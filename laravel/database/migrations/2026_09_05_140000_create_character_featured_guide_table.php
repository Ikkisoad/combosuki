<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // character.idcharacter is `bigint unsigned` on production but legacy
        // signed `int` on local WAMP snapshots; match whichever this
        // environment actually has so the FK constraint type-checks either way
        // (see character_link migration for the same pattern).
        $characterIdIsLegacyInt = Schema::getColumnType('character', 'idcharacter') === 'int';

        Schema::create('character_featured_guide', function (Blueprint $table) use ($characterIdIsLegacyInt) {
            $table->foreignId('list_idlist')->constrained('list', 'idlist')->cascadeOnDelete();

            if ($characterIdIsLegacyInt) {
                $table->integer('character_idcharacter');
                $table->foreign('character_idcharacter')->references('idcharacter')->on('character')->cascadeOnDelete();
            } else {
                $table->foreignId('character_idcharacter')->constrained('character', 'idcharacter')->cascadeOnDelete();
            }

            $table->primary(['list_idlist', 'character_idcharacter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_featured_guide');
    }
};
