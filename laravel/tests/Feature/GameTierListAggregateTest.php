<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\ListModel;
use App\Models\ResourceValue;
use App\Models\TierList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTierListAggregateTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_page_does_not_eagerly_load_guides_or_tier_lists(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        ListModel::create(['list_name' => 'A Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);

        $tierList = TierList::create(['title' => 'List A', 'game_idgame' => $game->idgame]);
        $tierList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $response = $this->get(route('games.show', $game));

        $response->assertOk();
        $response->assertDontSee('A Guide');
        $response->assertDontSee('Median of');
        $response->assertSee(route('games.tabs.guides', $game), false);
        $response->assertSee(route('games.tabs.tier-lists', $game), false);
    }

    public function test_guides_tab_endpoint_returns_guides(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        ListModel::create(['list_name' => 'A Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);

        $response = $this->get(route('games.tabs.guides', $game));

        $response->assertOk();
        $response->assertSee('A Guide');
    }

    public function test_tier_lists_tab_endpoint_shows_median_tier_across_all_tier_lists(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $listA = TierList::create(['title' => 'List A', 'game_idgame' => $game->idgame]);
        $listA->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $listB = TierList::create(['title' => 'List B', 'game_idgame' => $game->idgame]);
        $listB->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'A', 'order' => 0]);

        $listC = TierList::create(['title' => 'List C', 'game_idgame' => $game->idgame]);
        $listC->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'A', 'order' => 0]);

        $response = $this->get(route('games.tabs.tier-lists', $game));

        $response->assertOk();
        $response->assertSee('Median of 3 community tier lists');

        // Median of S, A, A is A: it should render inside the A tier row, not the S row.
        $content = $response->getContent();
        $aRowPos = strpos($content, 'tier-a');
        $bRowPos = strpos($content, 'tier-b');
        $namePos = strpos($content, 'Valentine');

        $this->assertNotFalse($namePos);
        $this->assertGreaterThan($aRowPos, $namePos);
        $this->assertLessThan($bRowPos, $namePos);
    }

    public function test_tier_lists_tab_endpoint_filters_by_date_range(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $oldList = TierList::create(['title' => 'Old List', 'game_idgame' => $game->idgame]);
        $oldList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'F', 'order' => 0]);
        $oldList->forceFill(['created_at' => now()->subMonths(3)])->save();

        $recentList = TierList::create(['title' => 'Recent List', 'game_idgame' => $game->idgame]);
        $recentList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $response = $this->get(route('games.tabs.tier-lists', [
            'game' => $game,
            'tier_from' => now()->subWeek()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Median of 1 community tier list');
    }

    public function test_tier_lists_tab_endpoint_shows_empty_state_without_tier_lists(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->get(route('games.tabs.tier-lists', $game));

        $response->assertOk();
        $response->assertSee('No tier lists for this game yet in the selected range.');
    }

    /**
     * An even number of votes has no single middle rank: the median is the
     * average of the two middle ranks, rounded to the nearest whole rank via
     * round(). S=0, A=1: two S votes and one A vote sorted is [S, S, A] (odd,
     * trivial); to force an even count we need 2 votes exactly, e.g. one S
     * (rank 0) and one B (rank 2) -> average rank 1 -> A, not a tie between
     * the two original tiers.
     */
    public function test_tier_lists_tab_endpoint_rounds_an_even_vote_count_median_to_the_nearest_rank(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $listA = TierList::create(['title' => 'List A', 'game_idgame' => $game->idgame]);
        $listA->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $listB = TierList::create(['title' => 'List B', 'game_idgame' => $game->idgame]);
        $listB->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'B', 'order' => 0]);

        $response = $this->get(route('games.tabs.tier-lists', $game));

        $response->assertOk();

        // Median of S (rank 0) and B (rank 2) is rank 1 = A.
        $content = $response->getContent();
        $aRowPos = strpos($content, 'tier-a');
        $bRowPos = strpos($content, 'tier-b');
        $namePos = strpos($content, 'Valentine');

        $this->assertNotFalse($namePos);
        $this->assertGreaterThan($aRowPos, $namePos);
        $this->assertLessThan($bRowPos, $namePos);
    }

    public function test_tier_lists_tab_endpoint_date_range_boundaries_are_inclusive(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $list = TierList::create(['title' => 'Boundary List', 'game_idgame' => $game->idgame]);
        $list->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);
        $list->forceFill(['created_at' => now()->startOfDay()])->save();

        $response = $this->get(route('games.tabs.tier-lists', [
            'game' => $game,
            'tier_from' => now()->toDateString(),
            'tier_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Median of 1 community tier list');
    }

    public function test_tier_lists_tab_endpoint_splits_a_character_into_separate_rows_per_resource_value(): void
    {
        $game = Game::create(['name' => 'Melty Blood', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Sion', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Moon Type',
            'type' => 1,
            'primaryORsecundary' => 1,
            'include_in_tier_lists' => true,
        ]);
        $crescent = ResourceValue::create(['value' => 'Crescent', 'order' => 1, 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $full = ResourceValue::create(['value' => 'Full', 'order' => 2, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $list = TierList::create(['title' => 'Moon Type List', 'game_idgame' => $game->idgame]);
        $list->entries()->create(['character_idcharacter' => $character->idcharacter, 'resources_values_idResources_values' => $crescent->idResources_values, 'tier' => 'S', 'order' => 0]);
        $list->entries()->create(['character_idcharacter' => $character->idcharacter, 'resources_values_idResources_values' => $full->idResources_values, 'tier' => 'F', 'order' => 1]);

        $response = $this->get(route('games.tabs.tier-lists', $game));

        $response->assertOk();

        // The character's S and F rows must not merge into a single median tier.
        $content = $response->getContent();
        $sRowPos = strpos($content, 'tier-s');
        $aRowPos = strpos($content, 'tier-a');
        $fRowPos = strpos($content, 'tier-f');
        $namePositions = [];
        $offset = 0;

        while (($pos = strpos($content, 'Sion', $offset)) !== false) {
            $namePositions[] = $pos;
            $offset = $pos + 1;
        }

        $this->assertCount(2, $namePositions);
        $this->assertGreaterThan($sRowPos, $namePositions[0]);
        $this->assertLessThan($aRowPos, $namePositions[0]);
        $this->assertGreaterThan($fRowPos, $namePositions[1]);
    }
}
