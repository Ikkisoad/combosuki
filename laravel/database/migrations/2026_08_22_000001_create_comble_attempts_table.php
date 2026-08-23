<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user.iduser is `bigint unsigned` on production but a legacy signed
        // `int` on local WAMP snapshots; match whichever this environment
        // actually has so the FK constraint type-checks either way.
        $userIdIsLegacyInt = Schema::getColumnType('user', 'iduser') === 'int';

        Schema::create('comble_attempts', function (Blueprint $table) use ($userIdIsLegacyInt) {
            $table->id('idcomble_attempt');
            $table->date('day');

            if ($userIdIsLegacyInt) {
                $table->integer('user_iduser')->nullable();
                $table->foreign('user_iduser')->references('iduser')->on('user')->nullOnDelete();
            } else {
                $table->foreignId('user_iduser')->nullable()->constrained('user', 'iduser')->nullOnDelete();
            }

            $table->string('visitor_key');
            $table->unsignedTinyInteger('guesses');
            $table->boolean('won');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['day', 'visitor_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comble_attempts');
    }
};
