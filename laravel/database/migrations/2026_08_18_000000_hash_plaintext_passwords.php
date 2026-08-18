<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * game.globalPass, list.password and combo.password were compared with
 * hash_equals() against plaintext, i.e. stored as plaintext in the DB. This
 * widens the columns to fit a bcrypt hash (60 chars) and hashes whatever
 * plaintext values already exist. One-way: down() restores the column width
 * only, it cannot recover the original plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `game` MODIFY `globalPass` VARCHAR(255) NULL');
            DB::statement('ALTER TABLE `list` MODIFY `password` VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE `combo` MODIFY `password` VARCHAR(255) NULL');
        }

        DB::table('game')
            ->whereNotNull('globalPass')
            ->where('globalPass', '!=', '')
            ->get(['idgame', 'globalPass'])
            ->each(function ($game) {
                if (! str_starts_with($game->globalPass, '$2y$')) {
                    DB::table('game')->where('idgame', $game->idgame)->update([
                        'globalPass' => bcrypt($game->globalPass),
                    ]);
                }
            });

        DB::table('list')
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->get(['idlist', 'password'])
            ->each(function ($list) {
                if (! str_starts_with($list->password, '$2y$')) {
                    DB::table('list')->where('idlist', $list->idlist)->update([
                        'password' => bcrypt($list->password),
                    ]);
                }
            });

        DB::table('combo')
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->get(['idcombo', 'password'])
            ->each(function ($combo) {
                if (! str_starts_with($combo->password, '$2y$')) {
                    DB::table('combo')->where('idcombo', $combo->idcombo)->update([
                        'password' => bcrypt($combo->password),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `game` MODIFY `globalPass` VARCHAR(16) NULL');
            DB::statement('ALTER TABLE `list` MODIFY `password` VARCHAR(16) NOT NULL');
            DB::statement('ALTER TABLE `combo` MODIFY `password` VARCHAR(16) NULL');
        }
    }
};
