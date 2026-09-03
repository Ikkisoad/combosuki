<?php

namespace Tests\Feature\Security;

use App\Models\Button;
use App\Models\ButtonAlias;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FiltersCombos::applyFilters is the only place in the app that *builds* a
 * SQL fragment in a variable-length loop: one nested REPLACE(?, ?) per button
 * alias configured for the game, then one per ignored button, and finally
 * whereRaw("{$comboSql} {$operator} ?", [...]). Nothing user-controlled
 * reaches the SQL string itself today — only the number of placeholders does,
 * and $operator comes from a fixed ternary — but a binding-count or
 * binding-order slip here is precisely the mistake that turns into an
 * injection the moment someone "simplifies" the interpolation.
 *
 * These drive hostile values through the whole path from both directions: the
 * searcher's pattern, and the admin-configured alias and button names that
 * decide how many placeholders there are. The failure mode being caught is
 * usually a QueryException from a placeholder/binding mismatch rather than
 * silent data loss, so most tests assert both the right results and an intact
 * table.
 *
 * MatchController's selectRaw is deliberately not covered here: the only
 * interpolated value there comes from a hardcoded two-element array literal
 * in the loop that builds it, never from a request.
 */
class ComboSearchInjectionTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Fighter', 'game_idgame' => $this->game->idgame]);
    }

    private function makeCombo(string $notation): Combo
    {
        return Combo::create([
            'combo' => $notation,
            'character_idcharacter' => $this->character->idcharacter,
            'damage' => 0,
            'type' => 1,
        ]);
    }

    private function search(string $pattern, int $mode = 2): TestResponse
    {
        return $this->get(route('games.combos.index', $this->game).'?'.http_build_query([
            'combo' => $pattern,
            'combolike' => $mode,
        ]));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function sqlInjectionPayloads(): array
    {
        return [
            'boolean tautology' => ["' OR 1=1 --"],
            'statement terminator' => ["'; DROP TABLE combo; --"],
            'union select' => ["' UNION SELECT null, null, null --"],
            'escaped quote' => ["\\' OR '1'='1"],
            'comment injection' => ['5A/*'.'*/OR/*'.'*/1=1'],
            'stacked update' => ["'; UPDATE user SET is_admin = 1; --"],
            'double quote' => ['" OR ""="'],
            'null byte' => ["5A\0OR 1=1"],
            'percent wildcard' => ['%'],
            'underscore wildcard' => ['_'],
            'backslash' => ['\\'],
            'question mark' => ['?'],
        ];
    }

    #[DataProvider('sqlInjectionPayloads')]
    public function test_a_hostile_search_pattern_is_treated_as_data_and_leaves_the_database_intact(string $payload): void
    {
        $this->makeCombo('5A > 5B');
        $this->makeCombo('2C > 3D');

        $this->search($payload)->assertOk();

        // Nothing dropped, nothing updated, no privilege granted.
        $this->assertDatabaseCount('combo', 2);
        $this->assertDatabaseCount('user', 0);
        $this->assertDatabaseHas('combo', ['combo' => '5A > 5B']);
    }

    /**
     * The single most important case in this file. A '?' inside alias text
     * would become an extra placeholder if the alias were ever interpolated
     * into the SQL string instead of bound — the binding count would then no
     * longer match the placeholder count and the query would throw.
     */
    public function test_an_alias_containing_a_question_mark_stays_bound_rather_than_becoming_a_placeholder(): void
    {
        $button = Button::create([
            'name' => 'LP+LK',
            'color' => '#ffffff',
            'match_type' => 'exact',
            'game_idgame' => $this->game->idgame,
        ]);

        ButtonAlias::create([
            'alias' => '?',
            'button_idbutton' => $button->idbutton,
            'game_idgame' => $this->game->idgame,
        ]);

        $combo = $this->makeCombo('LP+LK > 5B');

        $this->search('LP+LK')->assertOk()->assertSee($combo->combo);
    }

    public function test_an_alias_containing_sql_metacharacters_still_expands_correctly(): void
    {
        $button = Button::create([
            'name' => 'LP+LK',
            'color' => '#ffffff',
            'match_type' => 'exact',
            'game_idgame' => $this->game->idgame,
        ]);

        // Deliberately excludes % and _ : those are the LIKE wildcards
        // FiltersCombos itself wraps the pattern in, so an alias using one
        // would be substituted into the wrapper and change the search's
        // meaning — a nonsensical configuration, not an injection.
        foreach (["'", '"', ';', '\\'] as $aliasText) {
            ButtonAlias::create([
                'alias' => $aliasText,
                'button_idbutton' => $button->idbutton,
                'game_idgame' => $this->game->idgame,
            ]);
        }

        $combo = $this->makeCombo('LP+LK > 5B');

        // Four aliases => eight alias bindings, plus the ignored-token
        // bindings, plus the pattern. If any of those slipped out of order
        // the query would throw or match nothing.
        $this->search('LP+LK')->assertOk()->assertSee($combo->combo);
        $this->assertDatabaseCount('combo', 1);
    }

    /**
     * A button named '?' marked ignored adds a placeholder on the *other*
     * side of the nesting from the aliases, so this checks the two binding
     * groups stay in the order the SQL nests them.
     */
    public function test_an_ignored_button_named_with_a_question_mark_does_not_shift_the_binding_order(): void
    {
        $button = Button::create([
            'name' => 'LP+LK',
            'color' => '#ffffff',
            'match_type' => 'exact',
            'game_idgame' => $this->game->idgame,
        ]);

        ButtonAlias::create([
            'alias' => 'Throw',
            'button_idbutton' => $button->idbutton,
            'game_idgame' => $this->game->idgame,
        ]);

        Button::create([
            'name' => '?',
            'color' => '#ffffff',
            'match_type' => 'exact',
            'game_idgame' => $this->game->idgame,
            'ignored' => true,
        ]);

        $combo = $this->makeCombo('LP+LK?> 5B');

        $this->search('Throw')->assertOk()->assertSee($combo->combo);
    }

    /**
     * Aliases are expanded innermost and ignored tokens stripped outermost,
     * with longest-alias-first ordering so a short alias that is a substring
     * of a longer one can't clobber it. This only resolves correctly if both
     * binding groups line up with their placeholders.
     */
    public function test_alias_and_ignored_token_bindings_are_applied_in_the_order_the_sql_nests_them(): void
    {
        $throwButton = Button::create([
            'name' => 'LP+LK',
            'color' => '#ffffff',
            'match_type' => 'exact',
            'game_idgame' => $this->game->idgame,
        ]);

        $superButton = Button::create([
            'name' => '236236P',
            'color' => '#ff0000',
            'match_type' => 'exact',
            'game_idgame' => $this->game->idgame,
        ]);

        ButtonAlias::create(['alias' => 'Throw', 'button_idbutton' => $throwButton->idbutton, 'game_idgame' => $this->game->idgame]);
        ButtonAlias::create(['alias' => 'ThrowSuper', 'button_idbutton' => $superButton->idbutton, 'game_idgame' => $this->game->idgame]);

        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $this->game->idgame, 'ignored' => true]);

        $combo = $this->makeCombo('236236P > 5B');

        // "ThrowSuper" must win over "Throw" (longest first), and the ">"
        // must be stripped from the stored notation on the SQL side.
        $this->search('ThrowSuper5B')->assertOk()->assertSee($combo->combo);
    }

    public function test_a_search_with_many_configured_aliases_does_not_exhaust_the_bindings(): void
    {
        $button = Button::create([
            'name' => 'LP+LK',
            'color' => '#ffffff',
            'match_type' => 'exact',
            'game_idgame' => $this->game->idgame,
        ]);

        for ($i = 0; $i < 30; $i++) {
            ButtonAlias::create([
                'alias' => 'alias'.$i,
                'button_idbutton' => $button->idbutton,
                'game_idgame' => $this->game->idgame,
            ]);
        }

        $combo = $this->makeCombo('LP+LK > 5B');

        // 30 aliases => 60 bindings before the ignored tokens and pattern.
        $this->search('LP+LK')->assertOk()->assertSee($combo->combo);
    }

    /**
     * Documents that % and _ are not escaped before being wrapped in the LIKE
     * pattern (FiltersCombos builds '%'.$value.'%' directly). The impact is
     * search precision only: the query still runs inside Combo::visibleTo,
     * so a wildcard cannot reach a combo the requester couldn't already list.
     */
    public function test_a_wildcard_pattern_cannot_widen_results_past_the_visibility_scope(): void
    {
        $visible = $this->makeCombo('5A > 5B');
        $visible->update(['verified' => true]);

        $hidden = $this->makeCombo('SECRET > COMBO');
        $hidden->update([
            'verified' => false,
            'user_iduser' => User::create(['nickname' => 'author', 'password' => 'password123'])->iduser,
        ]);

        $response = $this->search('%')->assertOk();

        $response->assertSee($visible->combo);
        $response->assertDontSee($hidden->combo);
    }
}
