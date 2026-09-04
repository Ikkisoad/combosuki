<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the columns FiltersCombos::applyOrdering() and several listing
 * pages sort/filter by (see the "long loading times" performance
 * investigation) — without these, every combo search/listing and every
 * "most viewed" page forces a full table scan + filesort, and it only gets
 * slower as these tables grow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combo', function (Blueprint $table) {
            $table->index('damage');
            $table->index('submited');
            $table->index('type');
            $table->index('verified');
            $table->index('views');
        });

        Schema::table('game', function (Blueprint $table) {
            $table->index('views');
        });

        Schema::table('list', function (Blueprint $table) {
            $table->index('views');
        });

        Schema::table('tier_list', function (Blueprint $table) {
            $table->index('views');
        });
    }

    public function down(): void
    {
        Schema::table('combo', function (Blueprint $table) {
            $table->dropIndex(['damage']);
            $table->dropIndex(['submited']);
            $table->dropIndex(['type']);
            $table->dropIndex(['verified']);
            $table->dropIndex(['views']);
        });

        Schema::table('game', function (Blueprint $table) {
            $table->dropIndex(['views']);
        });

        Schema::table('list', function (Blueprint $table) {
            $table->dropIndex(['views']);
        });

        Schema::table('tier_list', function (Blueprint $table) {
            $table->dropIndex(['views']);
        });
    }
};
