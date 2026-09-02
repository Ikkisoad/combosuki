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
        // environment actually has so the FK constraint type-checks either
        // way (see character_button_alias migration for the same pattern).
        // character_default_queries.idquery is always a modern bigint
        // unsigned id() column (created in this repo's history), so it
        // needs no such check.
        $characterIdIsLegacyInt = Schema::getColumnType('character', 'idcharacter') === 'int';

        Schema::create('character_default_query_character', function (Blueprint $table) use ($characterIdIsLegacyInt) {
            // Explicit short constraint names below — the auto-generated
            // ones (table_column_foreign) exceed MySQL's 64-character
            // identifier limit for this table's long name.
            $table->unsignedBigInteger('character_default_query_idquery');
            $table->foreign('character_default_query_idquery', 'cdqc_query_foreign')
                ->references('idquery')->on('character_default_queries')->cascadeOnDelete();

            if ($characterIdIsLegacyInt) {
                $table->integer('character_idcharacter');
            } else {
                $table->unsignedBigInteger('character_idcharacter');
            }
            $table->foreign('character_idcharacter', 'cdqc_character_foreign')
                ->references('idcharacter')->on('character')->cascadeOnDelete();

            $table->primary(['character_default_query_idquery', 'character_idcharacter'], 'cdqc_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_default_query_character');
    }
};
