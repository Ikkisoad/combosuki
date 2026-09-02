<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\ComboDamageHistory;
use App\Models\Game;
use App\Models\GamePatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboDamageHistoryBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfillMigration(): void
    {
        $migration = require database_path('migrations/2026_09_02_000100_backfill_combo_damage_histories.php');
        $migration->up();
    }

    public function test_backfill_seeds_a_baseline_row_from_each_combos_current_damage_and_patch(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $patch = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.00', 'released_at' => now()->subDay()]);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        $combo = Combo::create([
            'combo' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 3000,
            'type' => 1,
            'patch_idgame_patch' => $patch->idgame_patch,
        ]);

        // Combo::saved() already recorded this via the live hook; delete it
        // to stand in for a pre-existing combo whose damage was set before
        // combo_damage_histories existed (so the hook never ran for it).
        ComboDamageHistory::query()->delete();

        $this->assertSame(0, ComboDamageHistory::count());

        $this->runBackfillMigration();

        $this->assertSame(1, ComboDamageHistory::count());
        $this->assertDatabaseHas('combo_damage_histories', [
            'combo_idcombo' => $combo->idcombo,
            'patch_idgame_patch' => $patch->idgame_patch,
            'damage' => 3000,
        ]);
    }

    public function test_backfill_skips_combos_missing_damage_or_patch(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $patch = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.00', 'released_at' => now()->subDay()]);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'damage' => null, 'type' => 1, 'patch_idgame_patch' => $patch->idgame_patch]);
        Combo::create(['combo' => 'B', 'character_idcharacter' => $character->idcharacter, 'damage' => 3000, 'type' => 1, 'patch_idgame_patch' => null]);

        ComboDamageHistory::query()->delete();

        $this->runBackfillMigration();

        $this->assertSame(0, ComboDamageHistory::count());
    }

    public function test_backfill_does_not_duplicate_a_row_already_recorded_live(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $patch = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.00', 'released_at' => now()->subDay()]);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        $combo = Combo::create([
            'combo' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 3000,
            'type' => 1,
            'patch_idgame_patch' => $patch->idgame_patch,
        ]);

        $this->assertSame(1, ComboDamageHistory::count());

        $this->runBackfillMigration();

        $this->assertSame(1, ComboDamageHistory::count());
        $this->assertDatabaseHas('combo_damage_histories', [
            'combo_idcombo' => $combo->idcombo,
            'patch_idgame_patch' => $patch->idgame_patch,
            'damage' => 3000,
        ]);
    }
}
