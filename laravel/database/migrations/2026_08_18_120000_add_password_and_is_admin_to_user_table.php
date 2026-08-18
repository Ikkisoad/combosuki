<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('password')->nullable()->after('nickname');
            $table->boolean('is_admin')->default(false)->after('trusted_user');
            $table->unique('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropUnique(['nickname']);
            $table->dropColumn(['password', 'is_admin']);
        });
    }
};
