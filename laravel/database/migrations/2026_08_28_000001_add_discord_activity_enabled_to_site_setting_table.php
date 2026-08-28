<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_setting', function (Blueprint $table) {
            // Defaults to OFF, unlike discord_integration_enabled: the
            // Activity is brand new (not the case for the OAuth flag, which
            // was already live in production when its migration ran) and
            // its Discord Developer Portal URL Mapping currently points at
            // the site root rather than the Activity route, which blanks
            // the iframe — so this must not switch itself on by deploying.
            $table->boolean('discord_activity_enabled')->default(false)->after('discord_integration_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('site_setting', function (Blueprint $table) {
            $table->dropColumn('discord_activity_enabled');
        });
    }
};
