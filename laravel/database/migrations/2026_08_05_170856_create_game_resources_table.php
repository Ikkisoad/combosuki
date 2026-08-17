<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_resources', function (Blueprint $table) {
            $table->id('idgame_resources');
            $table->foreignId('game_idgame')->constrained('game', 'idgame')->cascadeOnDelete();
            $table->string('text_name', 45);
            $table->integer('type')->nullable();
            $table->integer('primaryORsecundary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_resources');
    }
};
