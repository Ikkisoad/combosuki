<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GamePatch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * RefreshDatabase leaves the DB on the final schema (legacy `game.patch`/
 * `combo.patch` already dropped by the later cleanup migration), so these
 * tests temporarily re-add the legacy columns, seed free-text patch data
 * directly via the query builder (bypassing the models, which no longer
 * know about those columns), and re-run the backfill migration's up()
 * in isolation to verify the synthesized chain. The schema changes made
 * here are rolled back with the rest of the test's DB transaction.
 */
class GamePatchBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function addLegacyPatchColumns(): void
    {
        Schema::table('game', fn (Blueprint $table) => $table->string('patch', 10)->nullable());
        Schema::table('combo', fn (Blueprint $table) => $table->string('patch', 10)->nullable());
    }

    private function runBackfillMigration(): void
    {
        $migration = require database_path('migrations/2026_08_28_020100_backfill_game_patches_data.php');
        $migration->up();
    }

    public function test_backfill_chains_distinct_patch_labels_by_earliest_combo_submission(): void
    {
        $this->addLegacyPatchColumns();

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        DB::table('game')->where('idgame', $game->idgame)->update(['patch' => '1.2']);

        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        $comboA = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => 1]);
        DB::table('combo')->where('idcombo', $comboA->idcombo)->update(['patch' => '1.0', 'created_at' => '2026-01-01 00:00:00']);

        $comboB = Combo::create(['combo' => 'B', 'character_idcharacter' => $character->idcharacter, 'type' => 1]);
        DB::table('combo')->where('idcombo', $comboB->idcombo)->update(['patch' => '1.1', 'created_at' => '2026-02-01 00:00:00']);

        // No combo ever carried "1.2" — it only ever lived in game.patch, so
        // it must fall back to the game's own timestamp and still end up
        // last (current) since it has no earlier combo evidence.
        $comboC = Combo::create(['combo' => 'C', 'character_idcharacter' => $character->idcharacter, 'type' => 1]);

        $this->runBackfillMigration();

        $patches = GamePatch::where('game_idgame', $game->idgame)->orderBy('released_at')->get();

        $this->assertCount(3, $patches);
        $this->assertSame(['1.0', '1.1', '1.2'], $patches->pluck('label')->all());

        $this->assertSame('2026-01-01', $patches[0]->released_at->toDateString());
        $this->assertSame('2026-02-01', $patches[0]->ended_at->toDateString());
        $this->assertSame('2026-02-01', $patches[1]->released_at->toDateString());
        $this->assertNotNull($patches[1]->ended_at);
        $this->assertNull($patches[2]->ended_at);

        $this->assertSame($patches[0]->idgame_patch, $comboA->fresh()->patch_idgame_patch);
        $this->assertSame($patches[1]->idgame_patch, $comboB->fresh()->patch_idgame_patch);
        $this->assertNull($comboC->fresh()->patch_idgame_patch);
    }

    public function test_backfill_creates_no_rows_for_a_game_with_no_patch_data(): void
    {
        $this->addLegacyPatchColumns();

        $game = Game::create(['name' => 'Untouched Game', 'complete' => 1, 'modPass' => 'secret']);

        $this->runBackfillMigration();

        $this->assertSame(0, GamePatch::where('game_idgame', $game->idgame)->count());
        $this->assertNull($game->currentPatch);
    }
}
