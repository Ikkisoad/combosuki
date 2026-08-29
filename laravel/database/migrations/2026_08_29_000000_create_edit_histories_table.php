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

        Schema::create('edit_histories', function (Blueprint $table) use ($userIdIsLegacyInt) {
            $table->id();

            if ($userIdIsLegacyInt) {
                $table->integer('user_iduser')->nullable();
                $table->foreign('user_iduser')
                    ->references('iduser')->on('user')
                    ->nullOnDelete();
            } else {
                $table->foreignId('user_iduser')
                    ->nullable()
                    ->constrained('user', 'iduser')
                    ->nullOnDelete();
            }

            $table->string('editable_type');
            $table->unsignedBigInteger('editable_id');
            $table->string('action')->default('updated');
            $table->timestamp('created_at')->nullable();

            $table->index(['editable_type', 'editable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edit_histories');
    }
};
