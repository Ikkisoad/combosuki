<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0);
        });

        Schema::table('combo', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0);
        });

        Schema::table('list', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0);
        });

        Schema::table('tier_list', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('game', function (Blueprint $table) {
            $table->dropColumn('views');
        });

        Schema::table('combo', function (Blueprint $table) {
            $table->dropColumn('views');
        });

        Schema::table('list', function (Blueprint $table) {
            $table->dropColumn('views');
        });

        Schema::table('tier_list', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
