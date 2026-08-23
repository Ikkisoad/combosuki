<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comble_day_views', function (Blueprint $table) {
            $table->id('idcomble_day_view');
            $table->date('day')->unique();
            $table->unsignedInteger('views')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comble_day_views');
    }
};
