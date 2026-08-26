<?php

namespace Tests\Feature;

use App\Models\Game;
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
}
