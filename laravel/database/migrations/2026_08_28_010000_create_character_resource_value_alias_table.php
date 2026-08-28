<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // character.idcharacter and resources_values.idResources_values are
        // `bigint unsigned` on production but legacy signed `int` on local
        // WAMP snapshots; match whichever this environment actually has so
        // each FK constraint type-checks either way (see
        // character_game_resources / match_resources migrations for the
        // same pattern).
        $characterIdIsLegacyInt = Schema::getColumnType('character', 'idcharacter') === 'int';
        $resourceValueIdIsLegacyInt = Schema::getColumnType('resources_values', 'idResources_values') === 'int';

        Schema::create('character_resource_value_alias', function (Blueprint $table) use ($characterIdIsLegacyInt, $resourceValueIdIsLegacyInt) {
            $table->id('idcharacterresourcevaluealias');
            $table->string('alias', 45);
            $table->string('icon')->nullable();

            // Explicit (shorter) FK constraint names below: the default
            // auto-generated name for the resources_values FK exceeds
            // MySQL's 64-character identifier limit given this table's name.
            if ($characterIdIsLegacyInt) {
                $table->integer('character_idcharacter');
                $table->foreign('character_idcharacter', 'crva_character_foreign')->references('idcharacter')->on('character')->cascadeOnDelete();
            } else {
                $table->foreignId('character_idcharacter')->constrained('character', 'idcharacter', 'crva_character_foreign')->cascadeOnDelete();
            }

            if ($resourceValueIdIsLegacyInt) {
                $table->integer('resources_values_idResources_values');
                $table->foreign('resources_values_idResources_values', 'crva_resource_value_foreign')->references('idResources_values')->on('resources_values')->cascadeOnDelete();
            } else {
                $table->foreignId('resources_values_idResources_values')->constrained('resources_values', 'idResources_values', 'crva_resource_value_foreign')->cascadeOnDelete();
            }

            $table->timestamps();

            $table->unique(['character_idcharacter', 'resources_values_idResources_values'], 'character_resource_value_alias_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_resource_value_alias');
    }
};
