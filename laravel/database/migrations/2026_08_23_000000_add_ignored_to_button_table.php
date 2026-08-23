<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('button', function (Blueprint $table) {
            $table->boolean('ignored')->default(false)->after('match_type');
        });

        // Combo-notation searches used to always strip '>' hardcoded; mark
        // existing '>' buttons ignored so search behavior doesn't change.
        DB::table('button')->where('name', '>')->update(['ignored' => true]);
    }

    public function down(): void
    {
        Schema::table('button', function (Blueprint $table) {
            $table->dropColumn('ignored');
        });
    }
};
