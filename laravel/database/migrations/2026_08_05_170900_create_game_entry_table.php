<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_entry', function (Blueprint $table) {
            $table->id('entryid');
            $table->string('title', 100);
            $table->foreignId('gameid')->constrained('game', 'idgame')->cascadeOnDelete();
            $table->integer('order')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_entry');
    }
};
