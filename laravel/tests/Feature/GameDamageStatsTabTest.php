<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Regression test: the cached payload used to include raw
     * Character models/Collections (see GameController::computeDamageStats()),
     * which crashed with "incomplete object... unserialize()" once a real
     * request round-tripped it through the file cache driver (this app's
     * default outside tests — see .env.example) instead of the test suite's
     * `array` driver, which never actually serializes anything and so never
     * caught it. computeDamageStats() now stores plain character_id ints and
     * damageStatsTab() re-hydrates the Character objects afterward — this
     * exercises the real serialize()/unserialize() round trip to guard
     * against that regressing.
     */
    public function test_damage_stats_survive_a_real_file_cache_round_trip(): void
    {
        config(['cache.default' => 'file']);
        Cache::forgetDriver('file');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $valentine = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter',
            'filters' => ['combo' => '2LK', 'combolike' => '0'],
            'order' => 0,
        ]);

        Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $valentine->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);

        // First request computes and writes the cache entry to disk.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertOk()
            ->assertSeeInOrder(['Highest Damage', 'Valentine', '300']);

        // Second request reads the same entry back through a real
        // unserialize() call — this is what crashed before the fix.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertOk()
            ->assertSeeInOrder(['Highest Damage', 'Valentine', '300']);

        Cache::store('file')->flush();
    }

    /**
     * Same idea as the create/delete invalidation tests below, but for an
     * edit to an *existing* combo's damage made through the real
     * ComboController::update() route (as opposed to constructing the model
     * directly) — the actual path a user editing a combo's damage goes
     * through, and worth covering on its own since it's a different Combo
     * write shape (update vs. create/delete) than those tests exercise.
     */
    public function test_cached_damage_stats_are_invalidated_when_a_combos_damage_is_edited(): void
    {
        config(['cache.default' => 'file']);
        Cache::forgetDriver('file');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $valentine = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 0]);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter',
            'filters' => ['combo' => '2LK', 'combolike' => '0'],
            'order' => 0,
        ]);

        $combo = Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $valentine->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => $type->entryid,
        ]);

        // Primes the cache with the combo's original damage.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSeeInOrder(['Highest Damage', 'Valentine', '300']);

        $this->actingAs($trusted)->post(route('combos.update', $combo), [
            'character_idcharacter' => $valentine->idcharacter,
            'listingtype' => $type->entryid,
            'combo' => '2LK > 236B',
            'damage' => 999,
        ])->assertRedirect(route('combos.show', $combo));

        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSeeInOrder(['Highest Damage', 'Valentine', '999']);

        Cache::store('file')->flush();
    }

    /**
     * Regression test: FiltersCombos::searchCombos() scopes results by
     * Combo::visibleTo(auth()->user()) — a trusted staff member sees every
     * combo, a guest/regular visitor only sees verified (or otherwise
     * vouched-for) ones. The cache is per-game, so without splitting it by
     * viewer tier (see DamageStatsCache::key()'s $trusted segment and
     * GameController::damageStatsTab()), a guest viewing the tab first would
     * cache a restricted result that a *trusted* visitor then gets served
     * too — hiding an unverified combo they should be able to see, which is
     * exactly what "damage stats aren't updating" looks like from a staff
     * member's perspective after adding one.
     */
    public function test_a_trusted_viewer_sees_an_unverified_combo_even_if_a_guest_cached_the_tab_first(): void
    {
        config(['cache.default' => 'file']);
        Cache::forgetDriver('file');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $valentine = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Any starter',
            'filters' => [],
            'order' => 0,
        ]);

        // Guest-submitted combos are excluded from the public/guest view
        // once *any* logged-in author exists in the mix (see
        // Combo::scopeVisibleToPublic()) — an unverified combo from a
        // regular (non-trusted, unproven) user is the clean case that's
        // hidden from the public tier but visible to a trusted one.
        $regularUser = User::create(['nickname' => 'newbie', 'password' => 'password123']);
        Combo::create([
            'combo' => 'AAA', 'character_idcharacter' => $valentine->idcharacter, 'submited' => now(),
            'damage' => 500, 'type' => 1, 'user_iduser' => $regularUser->iduser, 'verified' => 0,
        ]);

        // A guest views the tab first, priming the "public" cache bucket
        // without the unverified combo.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSee('Not enough combo data yet.');

        // A trusted staff member must still see it — served from a
        // separate "trusted" cache bucket, not the guest's cached result.
        $trusted = User::create(['nickname' => 'admin', 'password' => 'password123', 'trusted_user' => true]);
        $this->actingAs($trusted)
            ->get(route('games.tabs.damage-stats', $game))
            ->assertSeeInOrder(['Highest Damage', 'Valentine', '500']);

        Cache::store('file')->flush();
    }

    /**
     * The tab's aggregation is cached forever per game (see
     * DamageStatsCache) and only invalidated by Combo::booted() when a combo
     * for the game is written — this verifies the cache doesn't go stale.
     */
    public function test_cached_damage_stats_are_invalidated_when_a_combo_is_added(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $valentine = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter',
            'filters' => ['combo' => '2LK', 'combolike' => '0'],
            'order' => 0,
        ]);

        // First view with no matching combos yet primes the cache.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSee('Not enough combo data yet.');

        Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $valentine->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);

        // The new combo must show up immediately, not only after the cache
        // would otherwise have expired.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSeeInOrder(['Highest Damage', 'Valentine', '300']);
    }

    public function test_cached_damage_stats_are_invalidated_when_a_combo_is_deleted(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $valentine = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter',
            'filters' => ['combo' => '2LK', 'combolike' => '0'],
            'order' => 0,
        ]);

        $combo = Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $valentine->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);

        // Primes the cache with the combo present.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSeeInOrder(['Highest Damage', 'Valentine', '300']);

        $combo->delete();

        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSee('Not enough combo data yet.');
    }
}
