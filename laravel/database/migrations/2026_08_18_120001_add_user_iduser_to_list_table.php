<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list', function (Blueprint $table) {
            $table->foreignId('user_iduser')->nullable()->after('game_idgame')->constrained('user', 'iduser')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('list', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_iduser');
        });
    }
};
