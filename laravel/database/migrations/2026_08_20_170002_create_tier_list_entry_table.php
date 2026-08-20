<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_list_entry', function (Blueprint $table) {
            $table->id('idtier_list_entry');
            $table->foreignId('tier_list_idtier_list')->constrained('tier_list', 'idtier_list')->cascadeOnDelete();
            $table->foreignId('character_idcharacter')->constrained('character', 'idcharacter')->cascadeOnDelete();
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
