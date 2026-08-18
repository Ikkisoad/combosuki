<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character', function (Blueprint $table) {
            $table->id('idcharacter');
            $table->string('name', 45);
            $table->foreignId('game_idgame')->constrained('game', 'idgame')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character');
    }
};
