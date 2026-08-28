<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePatchModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_current_returns_only_the_open_ended_patch(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $historical = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        $current = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.1', 'released_at' => '2026-02-01']);

        $result = GamePatch::where('game_idgame', $game->idgame)->current()->get();

        $this->assertCount(1, $result);
        $this->assertSame($current->idgame_patch, $result->first()->idgame_patch);
        $this->assertTrue($current->isCurrent());
        $this->assertFalse($historical->isCurrent());
    }

    public function test_game_current_patch_relation_returns_the_open_ended_patch(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        $current = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.1', 'released_at' => '2026-02-01']);

        $this->assertSame($current->idgame_patch, $game->currentPatch?->idgame_patch);
    }

    public function test_game_current_patch_is_null_when_no_patches_exist(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $this->assertNull($game->currentPatch);
    }

    public function test_game_patches_relation_orders_newest_first(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $old = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        $new = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.1', 'released_at' => '2026-02-01']);

        $this->assertSame([$new->idgame_patch, $old->idgame_patch], $game->patches->pluck('idgame_patch')->all());
    }
}
