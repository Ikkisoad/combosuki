<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list', function (Blueprint $table) {
            $table->boolean('is_favorite_guide')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('list', function (Blueprint $table) {
            $table->dropColumn('is_favorite_guide');
        });
    }
};
