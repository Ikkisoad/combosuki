<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePatch;
use App\Models\TierList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierListIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(string $name): Game
    {
        return Game::create(['name' => $name, 'complete' => 1, 'modPass' => 'secret']);
    }

    public function test_filters_by_game(): void
    {
        $gameA = $this->makeGame('Game A');
        $gameB = $this->makeGame('Game B');

        $listA = TierList::create(['title' => 'List A', 'game_idgame' => $gameA->idgame]);
        $listB = TierList::create(['title' => 'List B', 'game_idgame' => $gameB->idgame]);

        $response = $this->get(route('tier-lists.index', ['game_idgame' => $gameA->idgame]));

        $response->assertOk();
        $response->assertSee('List A');
        $response->assertDontSee('List B');
    }

    public function test_filters_by_author_nickname(): void
    {
        $game = $this->makeGame('Game A');
        $author = User::create(['nickname' => 'FightingFan', 'password' => 'password123']);

        $ownList = TierList::create(['title' => 'Owned List', 'game_idgame' => $game->idgame, 'user_iduser' => $author->iduser]);
        $anonymousList = TierList::create(['title' => 'Anon List', 'game_idgame' => $game->idgame]);

        $response = $this->get(route('tier-lists.index', ['author' => 'fighting']));

        $response->assertOk();
        $response->assertSee('Owned List');
        $response->assertDontSee('Anon List');
    }

    public function test_filters_by_date_range(): void
    {
        $game = $this->makeGame('Game A');

        $inRange = TierList::create(['title' => 'In Range', 'game_idgame' => $game->idgame]);
        $inRange->forceFill(['created_at' => '2026-01-15'])->save();

        $outOfRange = TierList::create(['title' => 'Out Of Range', 'game_idgame' => $game->idgame]);
        $outOfRange->forceFill(['created_at' => '2025-01-15'])->save();

        $response = $this->get(route('tier-lists.index', ['date_from' => '2026-01-01', 'date_to' => '2026-01-31']));

        $response->assertOk();
        $response->assertSee('In Range');
        $response->assertDontSee('Out Of Range');
    }

    public function test_filters_by_patch(): void
    {
        $game = $this->makeGame('Game A');

        $oldPatch = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.1', 'released_at' => '2026-02-01']);

        $inOldPatch = TierList::create(['title' => 'In Old Patch', 'game_idgame' => $game->idgame]);
        $inOldPatch->forceFill(['created_at' => '2026-01-15'])->save();

        $inNewPatch = TierList::create(['title' => 'In New Patch', 'game_idgame' => $game->idgame]);
        $inNewPatch->forceFill(['created_at' => '2026-02-15'])->save();

        $response = $this->get(route('tier-lists.index', ['game_idgame' => $game->idgame, 'tier_patch' => $oldPatch->idgame_patch]));

        $response->assertOk();
        $response->assertSee('In Old Patch');
        $response->assertDontSee('In New Patch');
    }

    public function test_tier_patch_all_does_not_filter(): void
    {
        $game = $this->makeGame('Game A');

        GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01']);

        $listA = TierList::create(['title' => 'List A', 'game_idgame' => $game->idgame]);
        $listA->forceFill(['created_at' => '2025-01-15'])->save();

        $response = $this->get(route('tier-lists.index', ['game_idgame' => $game->idgame, 'tier_patch' => 'all']));

        $response->assertOk();
        $response->assertSee('List A');
    }

    /**
     * The "Game" and "Patch" selects are two independent HTML controls with
     * no JS keeping them in sync client-side beyond the game select's
     * auto-submit-on-change — a stale tier_patch id from a different game
     * can still reach the server (e.g. a race between changing both selects
     * before either submit fires). Server-side this must never leak another
     * game's tier lists into the results; it should behave as if no patch
     * filter were applied at all.
     */
    public function test_a_patch_id_belonging_to_a_different_game_is_ignored_not_leaked(): void
    {
        $gameA = $this->makeGame('Game A');
        $gameB = $this->makeGame('Game B');

        $patchForGameA = GamePatch::create(['game_idgame' => $gameA->idgame, 'label' => '1.0', 'released_at' => '2026-01-01']);

        $listA = TierList::create(['title' => 'List A', 'game_idgame' => $gameA->idgame]);
        $listB = TierList::create(['title' => 'List B', 'game_idgame' => $gameB->idgame]);

        $response = $this->get(route('tier-lists.index', ['game_idgame' => $gameB->idgame, 'tier_patch' => $patchForGameA->idgame_patch]));

        $response->assertOk();
        $response->assertSee('List B');
        $response->assertDontSee('List A');
    }
}
