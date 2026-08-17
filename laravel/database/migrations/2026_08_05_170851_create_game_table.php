<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game', function (Blueprint $table) {
            $table->id('idgame');
            $table->string('name', 100);
            $table->integer('complete')->nullable();
            $table->string('image')->nullable();
            $table->string('globalPass', 16)->nullable();
            $table->string('modPass', 60);
            $table->string('patch', 10)->nullable();
            $table->string('description')->nullable();
            $table->string('notation', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game');
    }
};
