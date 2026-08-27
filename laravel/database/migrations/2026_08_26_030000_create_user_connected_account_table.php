<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_connected_account', function (Blueprint $table) {
            $table->id('idconnection');
            $table->string('provider', 32);
            $table->string('provider_user_id', 64);
            $table->string('provider_nickname', 190)->nullable();

            // user.iduser is `bigint unsigned` on production but a legacy
            // signed `int` on local WAMP snapshots; match whichever this
            // environment actually has so the FK type-checks either way.
            $userIdIsLegacyInt = Schema::getColumnType('user', 'iduser') === 'int';

            if ($userIdIsLegacyInt) {
                $table->integer('user_iduser');
                $table->foreign('user_iduser')->references('iduser')->on('user')->cascadeOnDelete();
            } else {
                $table->foreignId('user_iduser')->constrained('user', 'iduser')->cascadeOnDelete();
            }

            $table->timestamps();

            // These two indexes — not the checks in DiscordAccountLinker — are
            // what actually make the one-to-one guarantee hold: a Discord
            // account backs at most one Combo好き account, and a Combo好き
            // account holds at most one connection per provider. The service's
            // checks exist to produce a friendly message before we get here.
            $table->unique(['provider', 'provider_user_id'], 'user_connected_account_provider_unique');
            $table->unique(['user_iduser', 'provider'], 'user_connected_account_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_connected_account');
    }
};
