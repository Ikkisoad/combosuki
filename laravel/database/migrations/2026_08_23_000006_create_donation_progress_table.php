<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_progress', function (Blueprint $table) {
            $table->id();
            $table->string('month');
            $table->decimal('goal', 8, 2)->default(0);
            $table->decimal('raised', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_progress');
    }
};
