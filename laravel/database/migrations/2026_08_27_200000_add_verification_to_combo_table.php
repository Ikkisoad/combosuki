<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The local WAMP dev DB has a legacy `int` PK on user that predates
        // the bigint-unsigned convention `$table->id()` produces on real
        // production — branch so this migration works against both. See
        // project_mysql_legacy_int_columns memory.
        $userIdIsLegacyInt = Schema::getColumnType('user', 'iduser') === 'int';

        Schema::table('combo', function (Blueprint $table) use ($userIdIsLegacyInt) {
            if ($userIdIsLegacyInt) {
                $table->integer('verified_by_iduser')->nullable()->after('verified');
                $table->foreign('verified_by_iduser')
                    ->references('iduser')->on('user')
                    ->nullOnDelete();
            } else {
                $table->foreignId('verified_by_iduser')
                    ->nullable()
                    ->after('verified')
                    ->constrained('user', 'iduser')
                    ->nullOnDelete();
            }

            $table->timestamp('verified_at')->nullable()->after('verified_by_iduser');
        });
    }

    public function down(): void
    {
        Schema::table('combo', function (Blueprint $table) {
            $table->dropForeign(['verified_by_iduser']);
            $table->dropColumn(['verified_by_iduser', 'verified_at']);
        });
    }
};
