<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\TierList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTierListAggregateTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_page_shows_median_tier_across_all_tier_lists(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $listA = TierList::create(['title' => 'List A', 'game_idgame' => $game->idgame]);
        $listA->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $listB = TierList::create(['title' => 'List B', 'game_idgame' => $game->idgame]);
        $listB->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'A', 'order' => 0]);

        $listC = TierList::create(['title' => 'List C', 'game_idgame' => $game->idgame]);
        $listC->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'A', 'order' => 0]);

        $response = $this->get(route('games.show', $game));

        $response->assertOk();
        $response->assertSee('Median of 3 community tier lists');

        // Median of S, A, A is A: it should render inside the A tier row, not the S row.
        $pane = strstr($response->getContent(), 'id="tier-lists-pane"');
        $this->assertNotFalse($pane);

        $aRowPos = strpos($pane, 'tier-a');
        $bRowPos = strpos($pane, 'tier-b');
        $namePos = strpos($pane, 'Valentine');

        $this->assertNotFalse($namePos);
        $this->assertGreaterThan($aRowPos, $namePos);
        $this->assertLessThan($bRowPos, $namePos);
    }

    public function test_game_page_filters_aggregate_by_date_range(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $oldList = TierList::create(['title' => 'Old List', 'game_idgame' => $game->idgame]);
        $oldList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'F', 'order' => 0]);
        $oldList->forceFill(['created_at' => now()->subMonths(3)])->save();

        $recentList = TierList::create(['title' => 'Recent List', 'game_idgame' => $game->idgame]);
        $recentList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $response = $this->get(route('games.show', [
            'game' => $game,
            'tier_from' => now()->subWeek()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Median of 1 community tier list');
    }

    public function test_game_page_shows_empty_state_without_tier_lists(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->get(route('games.show', $game));

        $response->assertOk();
        $response->assertSee('No tier lists for this game yet in the selected range.');
    }
}
