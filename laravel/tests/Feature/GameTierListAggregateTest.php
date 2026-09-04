<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\GamePatch;
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

    /** @return array{0: Game, 1: Character, 2: GamePatch, 3: GamePatch} */
    private function makeGameWithTwoPatches(): array
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $oldPatch = GamePatch::create([
            'game_idgame' => $game->idgame,
            'label' => '1.0',
            'released_at' => now()->subMonths(6)->toDateString(),
            'ended_at' => now()->subWeek()->toDateString(),
        ]);

        $currentPatch = GamePatch::create([
            'game_idgame' => $game->idgame,
            'label' => '1.1',
            'released_at' => now()->subWeek()->toDateString(),
            'ended_at' => null,
        ]);

        return [$game, $character, $oldPatch, $currentPatch];
    }

    public function test_tier_lists_tab_endpoint_defaults_to_the_current_patchs_window(): void
    {
        [$game, $character] = $this->makeGameWithTwoPatches();

        $oldList = TierList::create(['title' => 'Old List', 'game_idgame' => $game->idgame]);
        $oldList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'F', 'order' => 0]);
        $oldList->forceFill(['created_at' => now()->subMonths(3)])->save();

        $recentList = TierList::create(['title' => 'Recent List', 'game_idgame' => $game->idgame]);
        $recentList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $response = $this->get(route('games.tabs.tier-lists', $game));

        $response->assertOk();
        $response->assertSee('Median of 1 community tier list');
    }

    public function test_tier_lists_tab_endpoint_tier_patch_all_clears_the_default_filter(): void
    {
        [$game, $character] = $this->makeGameWithTwoPatches();

        $oldList = TierList::create(['title' => 'Old List', 'game_idgame' => $game->idgame]);
        $oldList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'F', 'order' => 0]);
        $oldList->forceFill(['created_at' => now()->subMonths(3)])->save();

        $recentList = TierList::create(['title' => 'Recent List', 'game_idgame' => $game->idgame]);
        $recentList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $response = $this->get(route('games.tabs.tier-lists', ['game' => $game, 'tier_patch' => 'all']));

        $response->assertOk();
        $response->assertSee('Median of 2 community tier lists');
    }

    public function test_tier_lists_tab_endpoint_filters_by_an_explicit_historical_patch(): void
    {
        [$game, $character, $oldPatch] = $this->makeGameWithTwoPatches();

        $oldList = TierList::create(['title' => 'Old List', 'game_idgame' => $game->idgame]);
        $oldList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'F', 'order' => 0]);
        $oldList->forceFill(['created_at' => now()->subMonths(3)])->save();

        $recentList = TierList::create(['title' => 'Recent List', 'game_idgame' => $game->idgame]);
        $recentList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $response = $this->get(route('games.tabs.tier-lists', ['game' => $game, 'tier_patch' => $oldPatch->idgame_patch]));

        $response->assertOk();
        $response->assertSee('Median of 1 community tier list');

        // The only tier list in range is the old one (tier F), not the
        // recent one (tier S) — confirm Valentine lands in the F row.
        $content = $response->getContent();
        $fRowPos = strpos($content, 'tier-f');
        $namePos = strpos($content, 'Valentine');

        $this->assertNotFalse($namePos);
        $this->assertGreaterThan($fRowPos, $namePos);
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

    public function test_tier_lists_tab_endpoint_patch_window_lower_boundary_is_inclusive(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $patch = GamePatch::create([
            'game_idgame' => $game->idgame,
            'label' => '1.0',
            'released_at' => now()->toDateString(),
            'ended_at' => null,
        ]);

        $list = TierList::create(['title' => 'Boundary List', 'game_idgame' => $game->idgame]);
        $list->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);
        $list->forceFill(['created_at' => now()->startOfDay()])->save();

        $response = $this->get(route('games.tabs.tier-lists', ['game' => $game, 'tier_patch' => $patch->idgame_patch]));

        $response->assertOk();
        $response->assertSee('Median of 1 community tier list');
    }

    public function test_tier_lists_tab_endpoint_patch_window_upper_boundary_is_exclusive(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $oldPatch = GamePatch::create([
            'game_idgame' => $game->idgame,
            'label' => '1.0',
            'released_at' => now()->subMonth()->toDateString(),
            'ended_at' => now()->toDateString(),
        ]);
        GamePatch::create([
            'game_idgame' => $game->idgame,
            'label' => '1.1',
            'released_at' => now()->toDateString(),
            'ended_at' => null,
        ]);

        // Falls exactly on the boundary date, which belongs to the new
        // patch (1.1), not the old one (1.0)'s window.
        $list = TierList::create(['title' => 'Boundary List', 'game_idgame' => $game->idgame]);
        $list->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);
        $list->forceFill(['created_at' => now()->startOfDay()])->save();

        $response = $this->get(route('games.tabs.tier-lists', ['game' => $game, 'tier_patch' => $oldPatch->idgame_patch]));

        $response->assertOk();
        $response->assertSee('No tier lists for this game yet in the selected range.');
    }

    public function test_tier_lists_tab_endpoint_keeps_agreed_tier_despite_last_place_position(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $filler = Character::create(['name' => 'Filler', 'game_idgame' => $game->idgame]);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        // Both votes place Valentine in S, but always dead last in a two-slot
        // S tier (behind Filler). Every tier list agrees Valentine is S, so
        // it must stay in S — within-tier position only affects display
        // order, never which tier bucket a character lands in.
        $listA = TierList::create(['title' => 'List A', 'game_idgame' => $game->idgame]);
        $listA->entries()->create(['character_idcharacter' => $filler->idcharacter, 'tier' => 'S', 'order' => 0]);
        $listA->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 1]);

        $listB = TierList::create(['title' => 'List B', 'game_idgame' => $game->idgame]);
        $listB->entries()->create(['character_idcharacter' => $filler->idcharacter, 'tier' => 'S', 'order' => 0]);
        $listB->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 1]);

        $response = $this->get(route('games.tabs.tier-lists', $game));

        $response->assertOk();

        $content = $response->getContent();
        $sRowPos = strpos($content, 'tier-s');
        $aRowPos = strpos($content, 'tier-a');
        $fillerPos = strpos($content, 'Filler');
        $namePos = strpos($content, 'Valentine');

        // Both characters stay in S, since neither tier list disagreed.
        $this->assertNotFalse($fillerPos);
        $this->assertNotFalse($namePos);
        $this->assertGreaterThan($sRowPos, $fillerPos);
        $this->assertLessThan($aRowPos, $fillerPos);
        $this->assertGreaterThan($sRowPos, $namePos);
        $this->assertLessThan($aRowPos, $namePos);

        // Filler was always first in S, Valentine always last: position
        // still drives display order within the row.
        $this->assertLessThan($namePos, $fillerPos);
    }

    public function test_tier_lists_tab_endpoint_does_not_resort_a_single_tier_list_by_position(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $first = Character::create(['name' => 'Goku', 'game_idgame' => $game->idgame]);
        $second = Character::create(['name' => 'Vegeta', 'game_idgame' => $game->idgame]);
        $third = Character::create(['name' => 'Piccolo', 'game_idgame' => $game->idgame]);
        $fourth = Character::create(['name' => 'Gohan', 'game_idgame' => $game->idgame]);

        // A single tier list with four characters in S. There is no other
        // tier list to disagree with it, so all four must render in S —
        // none should be pulled down purely because of their drag position
        // within the tier.
        $list = TierList::create(['title' => 'Solo List', 'game_idgame' => $game->idgame]);
        $list->entries()->create(['character_idcharacter' => $first->idcharacter, 'tier' => 'S', 'order' => 0]);
        $list->entries()->create(['character_idcharacter' => $second->idcharacter, 'tier' => 'S', 'order' => 1]);
        $list->entries()->create(['character_idcharacter' => $third->idcharacter, 'tier' => 'S', 'order' => 2]);
        $list->entries()->create(['character_idcharacter' => $fourth->idcharacter, 'tier' => 'S', 'order' => 3]);

        $response = $this->get(route('games.tabs.tier-lists', $game));

        $response->assertOk();

        $content = $response->getContent();
        $sRowPos = strpos($content, 'tier-s');
        $aRowPos = strpos($content, 'tier-a');

        foreach ([$first, $second, $third, $fourth] as $character) {
            $namePos = strpos($content, $character->name);

            $this->assertNotFalse($namePos, "{$character->name} should be present");
            $this->assertGreaterThan($sRowPos, $namePos, "{$character->name} should be in the S row");
            $this->assertLessThan($aRowPos, $namePos, "{$character->name} should not be pulled into A");
        }
    }

    public function test_tier_lists_tab_endpoint_orders_a_tier_row_by_within_tier_position_not_alphabetically(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $zeta = Character::create(['name' => 'Zeta', 'game_idgame' => $game->idgame]);
        $alpha = Character::create(['name' => 'Alpha', 'game_idgame' => $game->idgame]);

        // Zeta is consistently placed ahead of Alpha within S across both
        // lists. Alphabetically Alpha < Zeta, but the render order should
        // follow the community's within-tier placement instead.
        foreach (['List A', 'List B'] as $title) {
            $list = TierList::create(['title' => $title, 'game_idgame' => $game->idgame]);
            $list->entries()->create(['character_idcharacter' => $zeta->idcharacter, 'tier' => 'S', 'order' => 0]);
            $list->entries()->create(['character_idcharacter' => $alpha->idcharacter, 'tier' => 'S', 'order' => 1]);
        }

        $response = $this->get(route('games.tabs.tier-lists', $game));

        $response->assertOk();

        $content = $response->getContent();
        $zetaPos = strpos($content, 'Zeta');
        $alphaPos = strpos($content, 'Alpha');

        $this->assertNotFalse($zetaPos);
        $this->assertNotFalse($alphaPos);
        $this->assertLessThan($alphaPos, $zetaPos);
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
