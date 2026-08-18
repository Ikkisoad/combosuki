<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list', function (Blueprint $table) {
            $table->id('idlist');
            $table->string('list_name', 100);
            $table->foreignId('game_idgame')->nullable()->constrained('game', 'idgame')->nullOnDelete();
            $table->string('password', 16);
            $table->integer('type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list');
    }
};
