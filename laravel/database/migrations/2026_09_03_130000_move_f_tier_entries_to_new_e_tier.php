<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An "E" tier was inserted between D and F. Every existing entry sat on what
 * was then the bottom tier ("F"), so those move up to E to keep the new F
 * genuinely empty until people start using it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tier_list_entry')->where('tier', 'F')->update(['tier' => 'E']);
    }

    public function down(): void
    {
        DB::table('tier_list_entry')->where('tier', 'E')->update(['tier' => 'F']);
    }
};
