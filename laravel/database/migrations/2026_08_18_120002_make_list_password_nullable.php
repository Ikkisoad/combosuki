<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * list.password was NOT NULL to support the now-removed per-list password
 * gate (see App\Services\GamePasswordChecker removal). Submissions are
 * authenticated instead, so this column is no longer populated on create.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `list` MODIFY `password` VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `list` MODIFY `password` VARCHAR(255) NOT NULL');
        }
    }
};
