<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_page', function (Blueprint $table) {
            $table->id('idListPage');
            $table->string('Title', 255);
            $table->text('Description')->nullable();
            $table->foreignId('idList')->constrained('list', 'idlist')->cascadeOnDelete();
            $table->integer('order')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_page');
    }
};
