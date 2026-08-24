<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_resources', function (Blueprint $table) {
            $table->boolean('include_in_matches')->default(false)->after('primaryORsecundary');
        });
    }

    public function down(): void
    {
        Schema::table('game_resources', function (Blueprint $table) {
            $table->dropColumn('include_in_matches');
        });
    }
};
