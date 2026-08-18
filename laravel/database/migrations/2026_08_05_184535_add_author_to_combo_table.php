<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combo', function (Blueprint $table) {
            $table->string('author', 45)->nullable()->after('patch');
        });
    }

    public function down(): void
    {
        Schema::table('combo', function (Blueprint $table) {
            $table->dropColumn('author');
        });
    }
};
