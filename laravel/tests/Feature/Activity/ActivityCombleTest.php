<?php

namespace Tests\Feature\Activity;

use App\Models\Character;
use App\Models\CombleAttempt;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\SiteSetting;
use App\Services\CombleDiscordProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ActivityCombleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00'));

        // The "array" cache store (see phpunit.xml) lives for the whole test
        // process, not just one test — see CombleDiscordProgress.
        Cache::flush();

        // discord_activity_enabled defaults to false (see
        // EnsureDiscordActivityEnabled) — this suite is about the routes'
        // own behavior once turned on; the flag itself is covered by
        // tests/Feature/Admin/SiteSettingTest.php.
        SiteSetting::current()->update(['discord_activity_enabled' => true]);
        SiteSetting::forgetCurrent();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_state_requires_a_bearer_token(): void
    {
        $this->getJson(route('activity.comble.state'))->assertStatus(401);
    }

    public function test_state_rejects_a_tampered_token(): void
    {
        $this->getJson(route('activity.comble.state'), ['Authorization' => 'Bearer not-a-real-token'])
            ->assertStatus(401);
    }

    public function test_state_rejects_an_expired_token(): void
    {
        $expired = Crypt::encryptString(json_encode(['uid' => '111', 'exp' => now()->subMinute()->timestamp]));

        $this->getJson(route('activity.comble.state'), ['Authorization' => 'Bearer '.$expired])
            ->assertStatus(401);
    }

    public function test_state_returns_the_rendered_fragment_for_a_valid_token(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $response = $this->getJson(route('activity.comble.state'), $this->authHeader('111'));

        $response->assertOk();
        $response->assertJsonStructure(['html']);
        $this->assertStringContainsString('5 guesses left', $response->json('html'));
    }

    public function test_a_correct_guess_wins_and_reveals_the_answer(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $response = $this->guess('111', $this->guessPayload($game, $character, $type));

        $response->assertOk();
        $this->assertStringContainsString('You got it!', $response->json('html'));
        $this->assertStringContainsString($character->name, $response->json('html'));
    }

    /**
     * The finished-puzzle state embeds the target combo's video via
     * <x-video-embed activity> (activity/_comble-game.blade.php) — its src
     * must go through Discord's Activity proxy (App\Support\ActivityProxyUrl)
     * rather than the raw youtube.com URL, or Discord's own CSP silently
     * blocks the iframe once this fragment is swapped into the page.
     */
    public function test_a_won_puzzle_embeds_the_video_through_the_activity_proxy(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $response = $this->guess('111', $this->guessPayload($game, $character, $type));

        $response->assertOk();
        $this->assertStringContainsString('/.proxy/youtube/embed/dQw4w9WgXcQ', $response->json('html'));
        $this->assertStringNotContainsString('www.youtube.com/embed', $response->json('html'));
    }

    public function test_a_wrong_guess_keeps_the_puzzle_in_progress(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Chun-Li');
        $wrongType = $this->makeType($wrongGame);

        $response = $this->guess('111', $this->guessPayload($wrongGame, $wrongCharacter, $wrongType));

        $response->assertOk();
        $this->assertStringContainsString('4 guesses left', $response->json('html'));
    }

    /**
     * Mirrors CombleTest::test_the_guess_table_scrolls_horizontally_on_narrow_screens()
     * for the Activity's own fork of the partial — this is the copy Discord
     * mobile actually renders once the handshake swaps it in.
     */
    public function test_the_guess_table_scrolls_horizontally_on_narrow_screens(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Chun-Li');
        $wrongType = $this->makeType($wrongGame);

        $response = $this->guess('111', $this->guessPayload($wrongGame, $wrongCharacter, $wrongType));

        $response->assertOk();
        $this->assertMatchesRegularExpression('#<div class="table-responsive[^"]*">\s*<table#', $response->json('html'));
    }

    /**
     * Mirrors CombleTest::test_the_damage_input_is_prefilled_with_the_last_guess()
     * — see ActivityCombleController::gameState()'s docblock for why this
     * partial keeps its own copy of the sticky logic instead of sharing it.
     * Uses a wrong game/character guess (like test_a_wrong_guess_keeps_the_puzzle_in_progress
     * above) so the puzzle — and the form — stays in progress; a correct
     * game+character guess wins outright regardless of damage.
     */
    public function test_the_damage_input_is_prefilled_with_the_last_guess(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['damage' => 3000]);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Chun-Li');
        $wrongType = $this->makeType($wrongGame);

        $response = $this->guess('111', $this->guessPayload($wrongGame, $wrongCharacter, $wrongType, 1000));

        $response->assertOk();
        $this->assertStringContainsString('id="comble-damage"', $response->json('html'));
        $this->assertStringContainsString('value="1000"', $response->json('html'));
    }

    public function test_a_finished_puzzle_rejects_further_guesses(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $this->guess('111', $this->guessPayload($game, $character, $type))->assertOk();

        $secondGame = $this->makeGame(['name' => 'Another Game']);
        $secondCharacter = $this->makeCharacter($secondGame, 'Blanka');
        $secondType = $this->makeType($secondGame);

        $response = $this->guess('111', $this->guessPayload($secondGame, $secondCharacter, $secondType));

        $response->assertStatus(409);
    }

    public function test_an_invalid_guess_returns_json_validation_errors(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $response = $this->postJson(route('activity.comble.guess'), [], $this->authHeader('111'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['game_id', 'character_id', 'listing_type_id', 'damage']);
    }

    /** Two different Discord users' guesses must never leak into each other's puzzle state. */
    public function test_two_players_progress_is_isolated(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Guile');
        $wrongType = $this->makeType($wrongGame);

        $this->guess('111', $this->guessPayload($wrongGame, $wrongCharacter, $wrongType))->assertOk();

        $response = $this->getJson(route('activity.comble.state'), $this->authHeader('222'));

        $this->assertStringContainsString('5 guesses left', $response->json('html'));
    }

    /**
     * The whole point of sharing CombleDiscordProgress between
     * DiscordCombleGame (bot) and ActivityCombleController (this) is that a
     * player's progress and finished-attempt row are the same regardless of
     * which Discord surface they play from — asserted here via the
     * "discord:"-prefixed visitor_key both surfaces write to.
     */
    public function test_a_finished_activity_game_records_the_same_visitor_key_format_the_bot_uses(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $this->guess('111', $this->guessPayload($game, $character, $type))->assertOk();

        $this->assertSame(1, CombleAttempt::count());
        $attempt = CombleAttempt::first();
        $this->assertSame('discord:111', $attempt->visitor_key);
        $this->assertTrue((bool) $attempt->won);
        $this->assertNull($attempt->user_iduser);
    }

    /**
     * The channel is captured by DiscordInteractionController when `/csk
     * comble` launches the Activity (see CombleDiscordProgress) — simulated
     * here directly, the same way DiscordCombleGame's own progress is seeded
     * directly elsewhere in this suite, rather than round-tripping through
     * the interactions endpoint.
     */
    public function test_a_finished_activity_game_announces_the_result_in_the_launch_channel(): void
    {
        config(['services.discord.bot_token' => 'fake-token']);
        Http::fake(['discord.com/*' => Http::response(['id' => 'posted'], 200)]);
        app(CombleDiscordProgress::class)->rememberChannel('111', '999888777');

        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $this->guess('111', $this->guessPayload($game, $character, $type))->assertOk();

        Http::assertSent(function ($request) use ($character) {
            if ($request->url() !== 'https://discord.com/api/v10/channels/999888777/messages') {
                return false;
            }

            $content = $request['content'];

            // Named by username, never a mention — a finished-puzzle
            // announcement shouldn't page anyone.
            $this->assertStringNotContainsString('<@111>', $content);
            $this->assertStringContainsString("finished today's Comble", $content);

            // Squares and score only — never the answer, same privacy rule
            // as DiscordCombleGame::publicStatus().
            $this->assertStringContainsString('1/5', $content);
            $this->assertStringNotContainsString($character->name, $content);

            // A "Play now" button lets anyone in the channel launch the
            // Activity for themselves.
            $this->assertSame('cb:launch', $request['components'][0]['components'][0]['custom_id']);

            return true;
        });
    }

    public function test_an_unfinished_activity_game_does_not_announce_anything(): void
    {
        config(['services.discord.bot_token' => 'fake-token']);
        Http::fake(['discord.com/*' => Http::response(['id' => 'posted'], 200)]);
        app(CombleDiscordProgress::class)->rememberChannel('111', '999888777');

        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Chun-Li');
        $wrongType = $this->makeType($wrongGame);

        $this->guess('111', $this->guessPayload($wrongGame, $wrongCharacter, $wrongType))->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/messages'));
    }

    /** No `/csk comble` launch means no remembered channel — the result must not fail the request, just skip the announcement. */
    public function test_a_finished_activity_game_without_a_remembered_channel_does_not_announce(): void
    {
        config(['services.discord.bot_token' => 'fake-token']);
        Http::fake(['discord.com/*' => Http::response(['id' => 'posted'], 200)]);

        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $this->guess('111', $this->guessPayload($game, $character, $type))->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/messages'));
    }

    public function test_the_endpoints_are_gated_behind_the_discord_integration_flag(): void
    {
        SiteSetting::current()->update(['discord_integration_enabled' => false]);
        SiteSetting::forgetCurrent();

        $this->getJson(route('activity.comble.state'), $this->authHeader('111'))->assertNotFound();
    }

    private function authHeader(string $discordUserId): array
    {
        $token = Crypt::encryptString(json_encode(['uid' => $discordUserId, 'exp' => now()->addHours(2)->timestamp]));

        return ['Authorization' => 'Bearer '.$token];
    }

    private function guess(string $discordUserId, array $payload): TestResponse
    {
        return $this->postJson(route('activity.comble.guess'), $payload, $this->authHeader($discordUserId));
    }

    private function guessPayload(Game $game, Character $character, GameEntry $type, float $damage = 3000, ?string $starter = null): array
    {
        return array_filter([
            'game_id' => $game->idgame,
            'character_id' => $character->idcharacter,
            'listing_type_id' => $type->entryid,
            'damage' => $damage,
            'starter' => $starter,
        ], fn ($value) => $value !== null);
    }

    private function makeGame(array $overrides = []): Game
    {
        return Game::create(array_merge([
            'name' => 'Test Fighter '.uniqid(),
            'complete' => 1,
            'modPass' => 'x',
        ], $overrides));
    }

    private function makeCharacter(Game $game, string $name = 'Ryu'): Character
    {
        return Character::create(['name' => $name, 'game_idgame' => $game->idgame]);
    }

    private function makeType(Game $game, string $title = 'Combo'): GameEntry
    {
        return GameEntry::create(['title' => $title, 'gameid' => $game->idgame, 'order' => 0]);
    }

    private function makeCombo(Character $character, GameEntry $type, array $overrides = []): Combo
    {
        return Combo::create(array_merge([
            'combo' => 'AAA BBB CCC DDD EEE',
            'character_idcharacter' => $character->idcharacter,
            'submited' => now(),
            'type' => $type->entryid,
            'damage' => 3000,
        ], $overrides));
    }
}
