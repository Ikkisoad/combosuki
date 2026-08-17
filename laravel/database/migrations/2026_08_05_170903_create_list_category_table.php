<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_category', function (Blueprint $table) {
            $table->id('idlist_category');
            $table->string('title', 50);
            $table->foreignId('list_idlist')->constrained('list', 'idlist')->cascadeOnDelete();
            $table->integer('order')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_category');
    }
};
