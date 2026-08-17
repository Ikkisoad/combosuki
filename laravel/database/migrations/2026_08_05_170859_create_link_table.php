<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link', function (Blueprint $table) {
            $table->id('idLink');
            $table->foreignId('idGame')->constrained('game', 'idgame')->cascadeOnDelete();
            $table->string('Title', 50);
            $table->string('Link', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link');
    }
};
