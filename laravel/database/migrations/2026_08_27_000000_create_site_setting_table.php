<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_setting', function (Blueprint $table) {
            $table->id('idsetting');

            // Defaults to ON: the Discord integration is already live in
            // production with real linked accounts, so applying this migration
            // must not switch off a working feature. The flag exists to be
            // turned off deliberately from the admin dashboard, never by a
            // deploy.
            $table->boolean('discord_integration_enabled')->default(true);

            $table->timestamps();
        });

        // Seeded here rather than left for SiteSetting::current()'s
        // firstOrCreate([]) to create lazily: with no unique constraint on
        // this single-row table, two concurrent first-ever requests could
        // otherwise both pass the "no row found" check and both insert,
        // leaving two rows with no guarantee which one later reads/writes
        // land on. Seeding means that race window never opens.
        DB::table('site_setting')->insert([
            'discord_integration_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_setting');
    }
};
