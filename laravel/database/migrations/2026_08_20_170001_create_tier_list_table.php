<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_list', function (Blueprint $table) {
            $table->id('idtier_list');
            $table->string('title', 100);
            $table->foreignId('game_idgame')->constrained('game', 'idgame')->cascadeOnDelete();
            $table->foreignId('user_iduser')->nullable()->constrained('user', 'iduser')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_list');
    }
};
