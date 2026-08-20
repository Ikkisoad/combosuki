<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_list_entry', function (Blueprint $table) {
            $table->integer('idtier_list_entry', true, false);
            $table->integer('tier_list_idtier_list', false, false);
            $table->foreign('tier_list_idtier_list')->references('idtier_list')->on('tier_list')->cascadeOnDelete();
            $table->integer('character_idcharacter', false, false);
            $table->foreign('character_idcharacter')->references('idcharacter')->on('character')->cascadeOnDelete();
            $table->string('tier', 1);
            $table->integer('order')->nullable();
            $table->timestamps();

            $table->unique(['tier_list_idtier_list', 'character_idcharacter'], 'tier_list_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_list_entry');
    }
};
