<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_challenge_picks', function (Blueprint $table) {
            $table->id('iddaily_challenge_pick');
            $table->date('day')->unique();
            $table->foreignId('query_idquery')->constrained('character_default_queries', 'idquery');
            $table->foreignId('character_idcharacter')->constrained('character', 'idcharacter');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_challenge_picks');
    }
};
