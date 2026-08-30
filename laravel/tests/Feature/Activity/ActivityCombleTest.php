<?php

namespace Tests\Feature\Activity;

use App\Models\Character;
use App\Models\CombleAttempt;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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
