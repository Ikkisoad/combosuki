<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    }

    public function down(): void
    {
        Schema::dropIfExists('site_setting');
    }
};
