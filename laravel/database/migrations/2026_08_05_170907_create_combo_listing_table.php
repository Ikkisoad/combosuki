<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo_listing', function (Blueprint $table) {
            $table->foreignId('idcombo')->constrained('combo', 'idcombo')->cascadeOnDelete();
            $table->foreignId('idlist')->constrained('list', 'idlist')->cascadeOnDelete();
            $table->string('comment', 45)->nullable();
            $table->foreignId('list_category_idlist_category')
                ->nullable()
                ->constrained('list_category', 'idlist_category')
                ->nullOnDelete();
            $table->timestamps();

            $table->primary(['idcombo', 'idlist']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_listing');
    }
};
