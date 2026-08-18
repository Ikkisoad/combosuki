<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('button', function (Blueprint $table) {
            $table->id('idbutton');
            $table->string('name', 45);
            $table->string('png', 45);
            $table->foreignId('game_idgame')->nullable()->constrained('game', 'idgame')->cascadeOnDelete();
            $table->integer('order')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('button');
    }
};
