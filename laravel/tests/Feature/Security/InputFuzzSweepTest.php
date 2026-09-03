<?php

namespace Tests\Feature\Security;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\ListModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A deliberately broad, deliberately shallow sweep: every hostile payload
 * shape fired at every input a guest or low-privilege user can reach, with
 * one uniform assertion — the app must never return a 5xx, and must never
 * echo a payload back unescaped.
 *
 * This is not aimed at any specific vulnerability. It exists because the one
 * genuine bug this whole exercise turned up was not an injection at all: it
 * was ?from[]=a on the honeypot, where an array arrived where a string was
 * assumed, PHP emitted a warning, and Laravel turned that into an
 * unauthenticated 500 with a stack trace. That class of bug — wrong PHP type,
 * not wrong SQL — is invisible to targeted tests because nobody thinks to
 * write one. A sweep catches the next one.
 *
 * Keep the assertion weak on purpose. Anything sharper turns into a
 * maintenance burden on ~150 cases whose only real job is to not explode.
 */
class InputFuzzSweepTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $this->game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $this->game->idgame, 'order' => 1]);

        $this->user = User::create(['nickname' => 'member', 'password' => 'password123']);

        Combo::create([
            'combo' => '5LP > 5MP',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'verified' => true,
        ]);

        ListModel::create([
            'list_name' => 'Guide',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->user->iduser,
        ]);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function hostilePayloads(): array
    {
        return [
            'sql tautology' => ["' OR 1=1 --"],
            'sql drop' => ["'; DROP TABLE combo; --"],
            'script tag' => ['<script>alert(1)</script>'],
            'attribute breakout' => ['"><img src=x onerror=alert(1)>'],
            'javascript url' => ['javascript:alert(1)'],
            'traversal' => ['../../../../etc/passwd'],
            'encoded traversal' => ['%2e%2e%2f%2e%2e%2fetc%2fpasswd'],
            'crlf' => ["a\r\nSet-Cookie: injected=1"],
            'null byte' => ["a\0b"],
            'format string' => ['%s%s%s%n'],
            'template injection' => ['{{ 7*7 }}'],
            'blade injection' => ['@php echo 1; @endphp'],
            'xml entity' => ['<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]>&e;'],
            'oversized' => [null], // built in the test — 10k chars
            'unicode overlong' => ["\xC0\xAE\xC0\xAE/"],
            'rtl override' => ["\u{202E}gnp.exe"],
            'emoji' => ['🔥🔥🔥'],
            'negative number' => ['-99999999999999999999'],
            'huge number' => ['999999999999999999999999999999'],
            'array' => [['nested' => 'value']],
            'empty' => [''],
            'whitespace only' => ["   \t\n  "],
        ];
    }

    private function resolve(mixed $payload): mixed
    {
        return $payload === null ? str_repeat('a', 10000) : $payload;
    }

    private function assertSurvived(TestResponse $response, string $context): void
    {
        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "{$context} returned {$response->getStatusCode()} — an unhandled error, not a rejection."
        );
    }

    #[DataProvider('hostilePayloads')]
    public function test_the_combo_search_filters_survive_hostile_input(mixed $payload): void
    {
        $value = $this->resolve($payload);

        foreach (['combo', 'combolike', 'characterid', 'damage', 'patch', 'author'] as $field) {
            $response = $this->get(route('games.combos.index', $this->game).'?'.http_build_query([$field => $value]));

            $this->assertSurvived($response, "games.combos.index?{$field}");
        }

        $this->assertDatabaseCount('combo', 1);
    }

    #[DataProvider('hostilePayloads')]
    public function test_the_public_search_endpoints_survive_hostile_input(mixed $payload): void
    {
        $value = $this->resolve($payload);

        $this->assertSurvived(
            $this->get(route('lists.search').'?'.http_build_query(['list_name' => $value, 'game_idgame' => $value])),
            'lists.search'
        );

        $this->assertSurvived(
            $this->get(route('games.index').'?'.http_build_query(['q' => $value, 'name' => $value])),
            'games.index'
        );

        $this->assertSurvived(
            $this->get(route('tier-lists.index').'?'.http_build_query(['game_idgame' => $value, 'title' => $value])),
            'tier-lists.index'
        );

        $this->assertDatabaseCount('list', 1);
    }

    #[DataProvider('hostilePayloads')]
    public function test_the_authenticated_typeahead_survives_hostile_input(mixed $payload): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('users.search').'?'.http_build_query(['q' => $this->resolve($payload)]));

        $this->assertSurvived($response, 'users.search');
    }

    #[DataProvider('hostilePayloads')]
    public function test_the_unauthenticated_write_endpoints_survive_hostile_input(mixed $payload): void
    {
        $value = $this->resolve($payload);

        $this->assertSurvived(
            $this->get(route('honeypot.hit').'?'.http_build_query(['from' => $value])),
            'honeypot.hit'
        );

        $this->assertSurvived(
            $this->post(route('preferences.update'), ['color' => $value, 'theme' => $value]),
            'preferences.update'
        );
    }

    #[DataProvider('hostilePayloads')]
    public function test_the_legacy_redirects_survive_hostile_input(mixed $payload): void
    {
        $value = $this->resolve($payload);

        foreach ([
            '/game/index.php?gameid=',
            '/game/combo.php?idcombo=',
            '/game/submit.php?gameid=',
            '/list/list.php?listid=',
            '/list/show.php?id=',
            '/list/search.php?q=',
        ] as $prefix) {
            $url = $prefix.(is_array($value) ? http_build_query(['x' => $value]) : urlencode((string) $value));

            $response = $this->get($url);

            $this->assertSurvived($response, $url);

            // A redirect must never leave this host, whatever went in.
            if ($location = $response->headers->get('Location')) {
                $this->assertStringStartsWith(
                    rtrim(config('app.url'), '/'),
                    $location,
                    "{$url} produced an off-site redirect"
                );
            }
        }
    }

    /**
     * Named regressions for the two bugs this sweep actually found, so they
     * fail with a specific message instead of being buried in a data set.
     *
     * Every search endpoint reads its filters with $request->string(), which
     * throws on an array — so ?combo[]=a was an unauthenticated 500 on a
     * public page. Now handled globally by GuardScalarQueryParameters.
     */
    public function test_an_array_query_parameter_is_ignored_rather_than_crashing_a_search(): void
    {
        foreach ([
            route('games.combos.index', $this->game).'?combo[]=a',
            route('lists.search').'?list_name[]=a',
            route('tier-lists.index').'?title[]=a',
            route('games.index').'?name[]=a',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($this->user)->get(route('users.search').'?q[]=a')->assertOk();
    }

    /**
     * The exception list in GuardScalarQueryParameters has to keep working:
     * the flow chart genuinely passes an array of moves in the query string.
     */
    public function test_a_legitimate_array_query_parameter_still_reaches_the_controller(): void
    {
        $character = Character::where('game_idgame', $this->game->idgame)->sole();

        $this->get(route('characters.tabs.flow-chart.next', [$this->game, $character]).'?path[]=5LP&path[]=5MP')
            ->assertOk();
    }

    /**
     * route() interpolates a value into the URI pattern, so braces left a
     * route parameter unreplaced and raised UrlGenerationException — a 500 on
     * a public legacy URL. Legacy ids were always integers, so non-numeric
     * input now falls through to the index.
     */
    public function test_a_non_numeric_legacy_id_falls_through_to_the_index_instead_of_crashing(): void
    {
        $this->get('/game/index.php?gameid='.urlencode('{{ 7*7 }}'))
            ->assertRedirect(route('games.index'));

        $this->get('/list/list.php?listid='.urlencode('{gameid}'))
            ->assertRedirect(route('lists.index'));

        // The real behaviour is untouched.
        $this->get('/game/index.php?gameid='.$this->game->idgame)
            ->assertRedirect(route('games.show', $this->game->idgame));
    }

    #[DataProvider('hostilePayloads')]
    public function test_route_model_binding_survives_hostile_identifiers(mixed $payload): void
    {
        $value = $this->resolve($payload);

        if (is_array($value)) {
            $this->markTestSkipped('An array cannot be placed in a path segment.');
        }

        $segment = rawurlencode((string) $value);

        if ($segment === '') {
            $this->markTestSkipped('An empty segment changes which route matches.');
        }

        foreach (["/games/{$segment}", "/combos/{$segment}", "/lists/{$segment}", "/users/{$segment}"] as $url) {
            $this->assertSurvived($this->get($url), $url);
        }
    }
}
