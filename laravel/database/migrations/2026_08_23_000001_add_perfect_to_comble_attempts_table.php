<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comble_attempts', function (Blueprint $table) {
            $table->boolean('perfect')->default(false)->after('won');
        });
    }

    public function down(): void
    {
        Schema::table('comble_attempts', function (Blueprint $table) {
            $table->dropColumn('perfect');
        });
    }
};
