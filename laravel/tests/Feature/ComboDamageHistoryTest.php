<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\ComboDamageHistory;
use App\Models\Game;
use App\Models\GamePatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboDamageHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(): Game
    {
        return Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
    }

    private function makePatch(Game $game, string $label): GamePatch
    {
        return GamePatch::create(['game_idgame' => $game->idgame, 'label' => $label, 'released_at' => now()->subDay()]);
    }

    private function makeCharacter(Game $game): Character
    {
        return Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);
    }

    public function test_creating_a_combo_records_a_damage_history_row(): void
    {
        $game = $this->makeGame();
        $patch = $this->makePatch($game, '1.00');
        $character = $this->makeCharacter($game);

        $combo = Combo::create([
            'combo' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 3500,
            'type' => 1,
            'patch_idgame_patch' => $patch->idgame_patch,
        ]);

        $this->assertSame(1, ComboDamageHistory::count());
        $this->assertDatabaseHas('combo_damage_histories', [
            'combo_idcombo' => $combo->idcombo,
            'patch_idgame_patch' => $patch->idgame_patch,
            'damage' => 3500,
        ]);
    }

    public function test_updating_damage_on_the_same_patch_corrects_the_existing_row(): void
    {
        $game = $this->makeGame();
        $patch = $this->makePatch($game, '1.00');
        $character = $this->makeCharacter($game);

        $combo = Combo::create([
            'combo' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 3500,
            'type' => 1,
            'patch_idgame_patch' => $patch->idgame_patch,
        ]);

        $combo->update(['damage' => 3600]);

        $this->assertSame(1, ComboDamageHistory::count());
        $this->assertDatabaseHas('combo_damage_histories', [
            'combo_idcombo' => $combo->idcombo,
            'patch_idgame_patch' => $patch->idgame_patch,
            'damage' => 3600,
        ]);
    }

    public function test_updating_damage_together_with_the_patch_records_a_new_row_and_keeps_the_old_one(): void
    {
        $game = $this->makeGame();
        $oldPatch = $this->makePatch($game, '1.00');
        $newPatch = $this->makePatch($game, '1.01');
        $character = $this->makeCharacter($game);

        $combo = Combo::create([
            'combo' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 3500,
            'type' => 1,
            'patch_idgame_patch' => $oldPatch->idgame_patch,
        ]);

        $combo->update(['damage' => 3000, 'patch_idgame_patch' => $newPatch->idgame_patch]);

        $this->assertSame(2, ComboDamageHistory::count());
        $this->assertDatabaseHas('combo_damage_histories', [
            'combo_idcombo' => $combo->idcombo,
            'patch_idgame_patch' => $oldPatch->idgame_patch,
            'damage' => 3500,
        ]);
        $this->assertDatabaseHas('combo_damage_histories', [
            'combo_idcombo' => $combo->idcombo,
            'patch_idgame_patch' => $newPatch->idgame_patch,
            'damage' => 3000,
        ]);
    }

    public function test_no_history_is_recorded_without_a_patch_or_without_damage(): void
    {
        $game = $this->makeGame();
        $patch = $this->makePatch($game, '1.00');
        $character = $this->makeCharacter($game);

        Combo::create([
            'combo' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 3500,
            'type' => 1,
            'patch_idgame_patch' => null,
        ]);

        Combo::create([
            'combo' => 'B',
            'character_idcharacter' => $character->idcharacter,
            'damage' => null,
            'type' => 1,
            'patch_idgame_patch' => $patch->idgame_patch,
        ]);

        $this->assertSame(0, ComboDamageHistory::count());
    }

    public function test_show_page_displays_damage_history_only_once_multiple_entries_exist(): void
    {
        $game = $this->makeGame();
        $oldPatch = $this->makePatch($game, '1.00');
        $newPatch = $this->makePatch($game, '1.01');
        $character = $this->makeCharacter($game);

        $combo = Combo::create([
            'combo' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 3500,
            'type' => 1,
            'patch_idgame_patch' => $oldPatch->idgame_patch,
        ]);

        $response = $this->get(route('combos.show', $combo));
        $response->assertOk();
        $response->assertDontSee('Damage history');

        $combo->update(['damage' => 3000, 'patch_idgame_patch' => $newPatch->idgame_patch]);

        $response = $this->get(route('combos.show', $combo));
        $response->assertOk();
        $response->assertSee('Damage history');
        $response->assertSeeInOrder(['1.00', '1.01']);
    }
}
