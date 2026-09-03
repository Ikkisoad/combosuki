<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * list.password was NOT NULL to support the now-removed per-list password
 * gate (see App\Services\GamePasswordChecker removal). Submissions are
 * authenticated instead, so this column is no longer populated on create.
 *
 * Applied on every driver, not just MySQL: tests run on SQLite (see
 * phpunit.xml) while production runs MySQL, so a MySQL-only branch here left
 * the test schema stricter than the real one — ListController::store omits
 * password entirely, which succeeds on production and raised a NOT NULL
 * violation under SQLite, making that endpoint untestable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `list` MODIFY `password` VARCHAR(255) NULL');

            return;
        }

        Schema::table('list', function (Blueprint $table): void {
            $table->string('password', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `list` MODIFY `password` VARCHAR(255) NOT NULL');

            return;
        }

        Schema::table('list', function (Blueprint $table): void {
            $table->string('password', 255)->nullable(false)->change();
        });
    }
};
