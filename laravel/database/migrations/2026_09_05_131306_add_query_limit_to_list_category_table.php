<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list_category', function (Blueprint $table) {
            $table->unsignedTinyInteger('query_limit')->nullable()->after('filters');
        });
    }

    public function down(): void
    {
        Schema::table('list_category', function (Blueprint $table) {
            $table->dropColumn('query_limit');
        });
    }
};
