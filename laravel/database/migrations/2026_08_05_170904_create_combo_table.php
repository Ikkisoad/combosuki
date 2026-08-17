<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo', function (Blueprint $table) {
            $table->id('idcombo');
            $table->longText('combo');
            $table->mediumText('comments')->nullable();
            $table->mediumText('video')->nullable();
            $table->foreignId('user_iduser')->nullable()->constrained('user', 'iduser')->nullOnDelete();
            $table->foreignId('character_idcharacter')->constrained('character', 'idcharacter')->cascadeOnDelete();
            $table->dateTime('submited')->nullable();
            $table->double('damage')->nullable();
            $table->integer('type');
            $table->integer('verified')->nullable();
            $table->string('patch', 10)->nullable();
            $table->string('password', 16)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo');
    }
};
