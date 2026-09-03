<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_command_usages', function (Blueprint $table) {
            $table->id('idcommand_usage');
            $table->string('command', 32)->unique();
            $table->unsignedInteger('uses')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_command_usages');
    }
};
