<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameDamageStatsTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_average_damage_and_top_character_per_default_query(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $valentine = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $painwheel = Character::create(['name' => 'Painwheel', 'game_idgame' => $game->idgame]);

        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter',
            'filters' => ['combo' => '2LK', 'combolike' => '0'],
            'order' => 0,
        ]);

        Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $valentine->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);
        Combo::create([
            'combo' => '2LK > 5C', 'character_idcharacter' => $painwheel->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1,
        ]);

        $response = $this->get(route('games.tabs.damage-stats', $game));

        $response->assertOk();
        $response->assertSee($query->label);
        // Query tab: highest damage is Valentine's 300, average across the two characters is 200.
        $response->assertSee('200');
        $response->assertSeeInOrder(['Highest Damage', 'Valentine', '300']);
    }

    public function test_evaluates_queries_sharing_a_group_label_together_as_one_tab(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $valentine = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $painwheel = Character::create(['name' => 'Painwheel', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'No support',
            'group_label' => 'Starter Group',
            'filters' => ['combo' => '2A', 'combolike' => '0'],
            'order' => 0,
        ]);
        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Support 1',
            'group_label' => 'Starter Group',
            'filters' => ['combo' => '5A', 'combolike' => '0'],
            'order' => 1,
        ]);

        // Valentine matches both member queries: the group should take her
        // best result (500, from the "Support 1" filter), not average or
        // pick whichever member happens to be evaluated first.
        Combo::create([
            'combo' => '2A > 236B', 'character_idcharacter' => $valentine->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);
        Combo::create([
            'combo' => '5A > 236B', 'character_idcharacter' => $valentine->idcharacter, 'submited' => now(), 'damage' => 500, 'type' => 1,
        ]);
        // Painwheel only matches the "No support" filter.
        Combo::create([
            'combo' => '2A > 5C', 'character_idcharacter' => $painwheel->idcharacter, 'submited' => now(), 'damage' => 200, 'type' => 1,
        ]);

        $response = $this->get(route('games.tabs.damage-stats', $game));

        $response->assertOk();
        // The group label is the one and only tab for this family of
        // queries — the individual member labels aren't surfaced anymore,
        // since their results are merged into a single evaluation.
        $response->assertSee('Starter Group');
        $response->assertDontSee('No support');
        $response->assertDontSee('Support 1');
        $response->assertSeeInOrder(['Highest Damage', 'Valentine', '500']);
        // Average across the two characters' best results: (500 + 200) / 2 = 350.
        $response->assertSee('350');
    }

    public function test_queries_without_a_group_label_stay_as_separate_tabs(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter',
            'filters' => ['combo' => '2LK', 'combolike' => '0'],
            'order' => 0,
        ]);
        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '5C starter',
            'filters' => ['combo' => '5C', 'combolike' => '0'],
            'order' => 1,
        ]);

        $response = $this->get(route('games.tabs.damage-stats', $game));

        $response->assertOk();
        $response->assertSee('2LK starter');
        $response->assertSee('5C starter');
    }

    public function test_character_scoped_query_only_contributes_data_for_scoped_characters(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $toph = Character::create(['name' => 'Toph', 'game_idgame' => $game->idgame]);
        $katara = Character::create(['name' => 'Katara', 'game_idgame' => $game->idgame]);
        $aang = Character::create(['name' => 'Aang', 'game_idgame' => $game->idgame]);

        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Command grab',
            'filters' => ['combo' => '622', 'combolike' => '0'],
            'order' => 0,
        ]);
        $query->characters()->attach([$toph->idcharacter, $katara->idcharacter]);

        Combo::create([
            'combo' => '622', 'character_idcharacter' => $toph->idcharacter, 'submited' => now(), 'damage' => 400, 'type' => 1,
        ]);
        Combo::create([
            'combo' => '622', 'character_idcharacter' => $katara->idcharacter, 'submited' => now(), 'damage' => 200, 'type' => 1,
        ]);
        // Aang isn't one of the scoped characters, but does happen to have a
        // combo that would otherwise match the query's filters — it must not
        // be picked up for him.
        Combo::create([
            'combo' => '622', 'character_idcharacter' => $aang->idcharacter, 'submited' => now(), 'damage' => 999, 'type' => 1,
        ]);

        $response = $this->get(route('games.tabs.damage-stats', $game));

        $response->assertOk();
        $response->assertSee($query->label);
        // Only the two scoped characters have data for this query: the
        // highest is Toph's 400 and the average is (400 + 200) / 2 = 300.
        // Aang's 999 combo is never evaluated against this query since he
        // isn't one of the scoped characters.
        $response->assertSeeInOrder(['Highest Damage', 'Toph', '400']);
        $response->assertSee('300');
        $response->assertDontSee('999');

        // The "Damage by Character" breakdown for this query should list
        // only the characters it's scoped to — Aang shouldn't appear in it
        // at all (not even as a "No data" row), even though he's a
        // character in the game with a matching combo of his own.
        // The pane id also appears earlier, in the nav-pill button that
        // targets it — search for the pane's own opening tag specifically
        // so the earlier Overview pane's content isn't swept in too.
        $html = $response->getContent();
        $panePosition = strpos($html, 'id="damage-stats-query-0-pane" role="tabpanel"');
        $this->assertNotFalse($panePosition);
        $paneHtml = substr($html, $panePosition);

        $this->assertStringContainsString('Toph', $paneHtml);
        $this->assertStringContainsString('Katara', $paneHtml);
        $this->assertStringNotContainsString('Aang', $paneHtml);
    }

    public function test_a_group_with_an_unscoped_member_query_still_lists_every_character(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $toph = Character::create(['name' => 'Toph', 'game_idgame' => $game->idgame]);
        $aang = Character::create(['name' => 'Aang', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Command grab',
            'group_label' => 'Starter Group',
            'filters' => ['combo' => '622', 'combolike' => '0'],
            'order' => 0,
        ])->characters()->attach([$toph->idcharacter]);

        // Shares the group with "Command grab" but isn't itself restricted
        // to any character.
        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Normal starter',
            'group_label' => 'Starter Group',
            'filters' => ['combo' => '2A', 'combolike' => '0'],
            'order' => 1,
        ]);

        $response = $this->get(route('games.tabs.damage-stats', $game));

        $response->assertOk();

        // The pane id also appears earlier, in the nav-pill button that
        // targets it — search for the pane's own opening tag specifically
        // so the earlier Overview pane's content isn't swept in too.
        $html = $response->getContent();
        $panePosition = strpos($html, 'id="damage-stats-query-0-pane" role="tabpanel"');
        $this->assertNotFalse($panePosition);
        $paneHtml = substr($html, $panePosition);

        // The group as a whole still applies to every character — Aang has
        // no data for either member query, but he's still listed (as "No
        // data") rather than being dropped like he would be if the group
        // took the restriction from "Command grab" alone.
        $this->assertStringContainsString('Aang', $paneHtml);
        $this->assertStringContainsString('Toph', $paneHtml);
    }

    public function test_query_pane_shows_no_data_message_when_no_combo_matches(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '5C starter',
            'filters' => ['combo' => '5C', 'combolike' => '0'],
            'order' => 0,
        ]);

        $response = $this->get(route('games.tabs.damage-stats', $game));

        $response->assertOk();
        $response->assertSee('5C starter');
        $response->assertSee('Not enough combo data yet.');
    }
}
