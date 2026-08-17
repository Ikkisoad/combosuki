<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id('idResources');
            $table->foreignId('combo_idcombo')->constrained('combo', 'idcombo')->cascadeOnDelete();
            $table->foreignId('Resources_values_idResources_values')
                ->constrained('resources_values', 'idResources_values')
                ->cascadeOnDelete();
            $table->double('number_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
