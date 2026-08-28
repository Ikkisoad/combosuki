<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game', function (Blueprint $table) {
            $table->dropColumn('patch');
        });

        Schema::table('combo', function (Blueprint $table) {
            $table->dropColumn('patch');
        });
    }

    /**
     * Schema-only restore — the original free-text values are gone for good
     * once this migration has run (superseded by game_patches/
     * patch_idgame_patch), so a rollback re-adds empty nullable columns
     * rather than recovering any data.
     */
    public function down(): void
    {
        Schema::table('game', function (Blueprint $table) {
            $table->string('patch', 10)->nullable();
        });

        Schema::table('combo', function (Blueprint $table) {
            $table->string('patch', 10)->nullable();
        });
    }
};
