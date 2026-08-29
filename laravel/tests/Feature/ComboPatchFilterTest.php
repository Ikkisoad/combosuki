<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GamePatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboPatchFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeCombo(Game $game, ?int $patchId, string $notation = 'A'): Combo
    {
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        return Combo::create([
            'combo' => $notation,
            'character_idcharacter' => $character->idcharacter,
            'damage' => 0,
            'type' => 1,
            'patch_idgame_patch' => $patchId,
        ]);
    }

    public function test_searching_by_patch_label_matches_via_the_relation(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $patch = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.02', 'released_at' => now()->subDay()]);

        $matching = $this->makeCombo($game, $patch->idgame_patch, '5A > 5B');
        $other = $this->makeCombo($game, null, '2C');

        $response = $this->get(route('games.combos.index', $game).'?patch=1.02');

        $response->assertOk();
        $response->assertSee($matching->combo);
        $response->assertViewHas('combos', function ($combos) use ($matching, $other) {
            $ids = $combos->pluck('idcombo');

            return $ids->contains($matching->idcombo) && ! $ids->contains($other->idcombo);
        });
    }
}
