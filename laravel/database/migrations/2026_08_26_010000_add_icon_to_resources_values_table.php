<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources_values', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('resources_values', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
