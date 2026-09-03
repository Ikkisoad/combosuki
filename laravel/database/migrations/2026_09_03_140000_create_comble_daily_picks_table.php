<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comble_daily_picks', function (Blueprint $table) {
            $table->id('idcomble_daily_pick');
            $table->date('day')->unique();
            $table->foreignId('combo_idcombo')->constrained('combo', 'idcombo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comble_daily_picks');
    }
};
