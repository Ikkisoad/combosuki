<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CharacterQueryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_trusted_user_can_add_update_and_delete_a_default_query(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);

        $this->actingAs($trusted);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => '2LK starter, no meter',
            'group_label' => '2LK starter',
            'order' => 1,
            'combo' => '2LK',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        $query = CharacterQuery::where('game_idgame', $game->idgame)->firstOrFail();
        $this->assertSame('2LK starter, no meter', $query->label);
        $this->assertSame('2LK starter', $query->group_label);
        $this->assertSame(1, $query->order);
        $this->assertSame(['combo' => '2LK', 'combolike' => '0'], $query->filters);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Update',
            'idquery' => $query->idquery,
            'label' => '2LK starter, updated',
            'group_label' => '',
            'order' => 2,
            'combo' => '2LK',
            'combolike' => '0',
            'damage' => '1000',
        ])->assertRedirect(route('admin.queries.index', $game));

        $query->refresh();
        $this->assertSame('2LK starter, updated', $query->label);
        $this->assertNull($query->group_label);
        $this->assertSame(2, $query->order);
        $this->assertSame(['combo' => '2LK', 'combolike' => '0', 'damage' => '1000'], $query->filters);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Delete',
            'idquery' => $query->idquery,
        ])->assertRedirect(route('admin.queries.index', $game));

        $this->assertDatabaseMissing('character_default_queries', ['idquery' => $query->idquery]);
    }

    public function test_a_query_can_be_restricted_to_multiple_characters(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $toph = Character::create(['name' => 'Toph', 'game_idgame' => $game->idgame]);
        $katara = Character::create(['name' => 'Katara', 'game_idgame' => $game->idgame]);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);

        $this->actingAs($trusted);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => 'Command grab',
            'group_label' => '',
            'character_idcharacters' => [$toph->idcharacter, $katara->idcharacter],
            'order' => 0,
            'combo' => '622',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        $query = CharacterQuery::where('game_idgame', $game->idgame)->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$toph->idcharacter, $katara->idcharacter],
            $query->characters()->pluck('idcharacter')->all()
        );

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Update',
            'idquery' => $query->idquery,
            'label' => 'Command grab',
            'group_label' => '',
            'character_idcharacters' => [$katara->idcharacter],
            'order' => 0,
            'combo' => '622',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        $this->assertSame([$katara->idcharacter], $query->characters()->pluck('idcharacter')->all());

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Update',
            'idquery' => $query->idquery,
            'label' => 'Command grab',
            'group_label' => '',
            'order' => 0,
            'combo' => '622',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        $this->assertSame([], $query->characters()->pluck('idcharacter')->all());
    }

    public function test_a_query_cannot_be_restricted_to_a_character_from_another_game(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $foreignCharacter = Character::create(['name' => 'Foreign', 'game_idgame' => $otherGame->idgame]);

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => 'Command grab',
            'character_idcharacters' => [$foreignCharacter->idcharacter],
            'order' => 0,
            'combo' => '622',
        ])->assertSessionHasErrors('character_idcharacters.0');

        $this->assertDatabaseMissing('character_default_queries', ['game_idgame' => $game->idgame]);
    }

    public function test_index_offers_existing_queries_as_copy_sources(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter, no meter',
            'filters' => ['combo' => '2LK'],
            'order' => 0,
        ]);

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $response = $this->get(route('admin.queries.index', $game));

        $response->assertOk();
        $response->assertSee('data-query-id="'.$query->idquery.'"', false);
        $response->assertSee('<option value="'.$query->idquery.'">2LK starter, no meter</option>', false);
    }

    public function test_index_renders_a_clear_button_next_to_the_characters_multi_select(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $response = $this->get(route('admin.queries.index', $game));

        $response->assertOk();
        $response->assertSee('name="character_idcharacters[]"', false);
        $response->assertSee('onclick="clearMultiSelect(this)"', false);
    }

    public function test_non_trusted_user_cannot_manage_queries(): void
    {
        // Non-JSON 403s are converted to a redirect + flash error by the
        // app's exception handler (bootstrap/app.php), not a raw 403 body.
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123']));

        $this->get(route('admin.queries.index', $game))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => 'Should not save',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('character_default_queries', ['game_idgame' => $game->idgame]);
    }

    /**
     * Regression test: GameController::damageStatsTab() caches its result
     * per game (see DamageStatsCache) and is invalidated by Combo writes,
     * but adding/editing/deleting a query never touches Combo — it has to be
     * invalidated here instead (see invalidateQueryDependentCaches()), or a
     * visitor who opened the tab before any query existed keeps seeing "This
     * game doesn't have any default queries configured yet." forever, even
     * after one is added.
     */
    public function test_damage_stats_tab_reflects_a_newly_added_query_even_if_viewed_before(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        // Primes the cache while no query exists yet.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSee("This game doesn't have any default queries configured yet.", false);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => '2LK starter',
            'group_label' => '',
            'order' => 0,
            'combo' => '2LK',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSee('2LK starter')
            ->assertDontSee("This game doesn't have any default queries configured yet.", false);
    }

    public function test_queries_cannot_be_managed_across_games(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $foreignQuery = CharacterQuery::create([
            'game_idgame' => $otherGame->idgame,
            'label' => 'Foreign',
            'filters' => ['combo' => '5A'],
            'order' => 0,
        ]);

        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Delete',
            'idquery' => $foreignQuery->idquery,
        ]);

        $this->assertDatabaseHas('character_default_queries', ['idquery' => $foreignQuery->idquery]);
    }

    /**
     * Regression test: the challenge calendar tab (ChallengeController::
     * calendarTab()) shares the same query-CRUD cache invalidation as the
     * damage-stats tab (see invalidateQueryDependentCaches(), which bumps
     * ChallengeStatsCache alongside forgetting DamageStatsCache) — this
     * verifies that path too, not just the damage-stats one above.
     */
    public function test_challenge_calendar_tab_reflects_a_newly_added_query_even_if_viewed_before(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        // Primes the (year, day) cache entries while no query exists yet.
        $this->getJson(route('challenge.tabs.calendar', ['year' => 2026]))
            ->assertExactJson(['days' => [], 'earliest' => null, 'today' => '2026-08-25']);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => 'Any starter',
            'group_label' => '',
            'order' => 0,
            'combo' => '',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        // DailyChallenge::earliestDate() only makes a query eligible starting
        // the day after it was created, so the query just created "now"
        // (2026-08-25) needs backdating for today to be in range — same as
        // ChallengeCalendarTabTest's direct-Model::create fixtures.
        $query = CharacterQuery::where('game_idgame', $game->idgame)->firstOrFail();
        $query->forceFill(['created_at' => Carbon::parse('2026-08-01 00:00:00')])->save();

        $this->getJson(route('challenge.tabs.calendar', ['year' => 2026]))
            ->assertJsonPath('days.2026-08-25', 'open');
    }

    /**
     * Same regression as above, for the challenge ranking tab.
     */
    public function test_challenge_ranking_tab_reflects_a_newly_added_query_even_if_viewed_before(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 0]);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $winner = User::create(['nickname' => 'Alice', 'password' => 'secret']);
        Combo::create([
            'combo' => 'AAA BBB', 'character_idcharacter' => $character->idcharacter,
            'submited' => now(), 'damage' => 1000, 'type' => $type->entryid,
            'user_iduser' => $winner->iduser, 'verified' => 1,
        ]);

        // Primes the cache: no query yet means nothing is rankable, even
        // though a matching combo already exists.
        $this->get(route('challenge.tabs.ranking'))->assertSee('No ranked combos yet');

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => 'Any starter',
            'group_label' => '',
            'order' => 0,
            'combo' => '',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        $query = CharacterQuery::where('game_idgame', $game->idgame)->firstOrFail();
        $query->forceFill(['created_at' => Carbon::parse('2026-08-01 00:00:00')])->save();

        $this->get(route('challenge.tabs.ranking'))->assertSee('Alice');
    }

    /**
     * Regression test companion to test_damage_stats_tab_reflects_a_newly_
     * added_query_even_if_viewed_before: that test only exercises the Add
     * action, this one covers Update.
     */
    public function test_damage_stats_tab_reflects_an_updated_query_even_if_viewed_before(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => '2LK starter',
            'group_label' => '',
            'order' => 0,
            'combo' => '2LK',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        // Primes the cache with the pre-update label.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSee('2LK starter');

        $query = CharacterQuery::where('game_idgame', $game->idgame)->firstOrFail();

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Update',
            'idquery' => $query->idquery,
            'label' => '2LK starter, updated',
            'group_label' => '',
            'order' => 0,
            'combo' => '2LK',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        // '2LK starter' is a substring of the updated label, so the stale
        // label is checked via its exact rendered form (see the nav-pill
        // button in damage-stats-tab.blade.php) rather than a plain
        // assertDontSee, which would false-fail against the new label too.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSee('2LK starter, updated')
            ->assertDontSee('>2LK starter<', false);
    }

    /**
     * Regression test companion to test_damage_stats_tab_reflects_a_newly_
     * added_query_even_if_viewed_before: that test only exercises the Add
     * action, this one covers Delete.
     */
    public function test_damage_stats_tab_reflects_a_deleted_query_even_if_viewed_before(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => '2LK starter',
            'group_label' => '',
            'order' => 0,
            'combo' => '2LK',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        // Primes the cache with the query present.
        $this->get(route('games.tabs.damage-stats', $game))
            ->assertSee('2LK starter')
            ->assertDontSee("This game doesn't have any default queries configured yet.", false);

        $query = CharacterQuery::where('game_idgame', $game->idgame)->firstOrFail();

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Delete',
            'idquery' => $query->idquery,
        ])->assertRedirect(route('admin.queries.index', $game));

        $this->get(route('games.tabs.damage-stats', $game))
            ->assertDontSee('2LK starter')
            ->assertSee("This game doesn't have any default queries configured yet.", false);
    }
}
