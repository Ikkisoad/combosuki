<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo_damage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_idcombo')->constrained('combo', 'idcombo')->cascadeOnDelete();
            $table->foreignId('patch_idgame_patch')->constrained('game_patches', 'idgame_patch')->cascadeOnDelete();
            $table->double('damage');
            $table->timestamps();

            $table->unique(['combo_idcombo', 'patch_idgame_patch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_damage_histories');
    }
};
