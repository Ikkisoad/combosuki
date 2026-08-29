<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // button.idbutton / character.idcharacter are `bigint unsigned` on
        // production but legacy signed `int` on local WAMP snapshots; match
        // whichever this environment actually has so the FK constraints
        // type-check either way.
        $buttonIdIsLegacyInt = Schema::getColumnType('button', 'idbutton') === 'int';
        $characterIdIsLegacyInt = Schema::getColumnType('character', 'idcharacter') === 'int';

        Schema::create('character_button_alias', function (Blueprint $table) use ($buttonIdIsLegacyInt, $characterIdIsLegacyInt) {
            $table->id('idcharacterbuttonalias');
            $table->string('alias', 45);

            if ($buttonIdIsLegacyInt) {
                $table->integer('button_idbutton');
                $table->foreign('button_idbutton')->references('idbutton')->on('button')->cascadeOnDelete();
            } else {
                $table->foreignId('button_idbutton')->constrained('button', 'idbutton')->cascadeOnDelete();
            }

            if ($characterIdIsLegacyInt) {
                $table->integer('character_idcharacter');
                $table->foreign('character_idcharacter')->references('idcharacter')->on('character')->cascadeOnDelete();
            } else {
                $table->foreignId('character_idcharacter')->constrained('character', 'idcharacter')->cascadeOnDelete();
            }

            $table->timestamps();

            $table->unique(['character_idcharacter', 'alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_button_alias');
    }
};
