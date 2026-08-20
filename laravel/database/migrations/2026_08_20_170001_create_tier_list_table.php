<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_list', function (Blueprint $table) {
            $table->integer('idtier_list', true, false);
            $table->string('title', 100);
            $table->integer('game_idgame', false, false);
            $table->foreign('game_idgame')->references('idgame')->on('game')->cascadeOnDelete();
            $table->integer('user_iduser', false, false)->nullable();
            $table->foreign('user_iduser')->references('iduser')->on('user')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_list');
    }
};
