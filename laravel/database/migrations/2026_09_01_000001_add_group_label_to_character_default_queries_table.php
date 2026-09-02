<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_default_queries', function (Blueprint $table) {
            $table->string('group_label', 150)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('character_default_queries', function (Blueprint $table) {
            $table->dropColumn('group_label');
        });
    }
};
