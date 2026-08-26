<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_resources', function (Blueprint $table) {
            $table->boolean('include_in_tier_lists')->default(false)->after('include_in_matches');
        });
    }

    public function down(): void
    {
        Schema::table('game_resources', function (Blueprint $table) {
            $table->dropColumn('include_in_tier_lists');
        });
    }
};
