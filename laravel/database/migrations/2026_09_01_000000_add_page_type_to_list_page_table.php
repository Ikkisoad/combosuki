<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list_page', function (Blueprint $table) {
            $table->string('page_type', 20)->default('text')->after('idList');
        });
    }

    public function down(): void
    {
        Schema::table('list_page', function (Blueprint $table) {
            $table->dropColumn('page_type');
        });
    }
};
