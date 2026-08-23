<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game', function (Blueprint $table) {
            $table->boolean('matches_enabled')->default(false)->after('notation');
            $table->string('matches_url', 255)->nullable()->after('matches_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('game', function (Blueprint $table) {
            $table->dropColumn(['matches_enabled', 'matches_url']);
        });
    }
};
