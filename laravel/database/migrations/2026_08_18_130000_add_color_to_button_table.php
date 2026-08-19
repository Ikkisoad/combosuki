<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('button', function (Blueprint $table) {
            $table->dropColumn('png');
            $table->string('color', 7)->default('#ffffff')->after('name');
            $table->string('match_type', 20)->default('exact')->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('button', function (Blueprint $table) {
            $table->dropColumn(['color', 'match_type']);
            $table->string('png', 45)->after('name');
        });
    }
};
