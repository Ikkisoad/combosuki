<?php

namespace Tests\Feature\Security;

use App\Models\Character;
use App\Models\CombleAttempt;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\SiteSetting;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * The Activity routes are registered outside the `web` group (see
 * routes/activity.php and bootstrap/app.php), so they carry no session and no
 * CSRF token. The Crypt-sealed Bearer token minted by ActivityAuthController
 * is therefore the entire identity story for those endpoints: whatever it
 * says the Discord user id is, that is who the request is.
 *
 * ActivityCombleTest covers the two easy rejections (no token, and a string
 * that isn't a token at all). These cover the ones an attacker who
 * understands the format would actually attempt — forging under their own
 * key, tampering with a real token's ciphertext, and abusing PHP's loose
 * typing to make an expiry that never expires.
 */
class ActivityTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00'));

        // The "array" cache store (see phpunit.xml) lives for the whole test
        // process, not just one test — see CombleDiscordProgress.
        Cache::flush();

        SiteSetting::current()->update(['discord_activity_enabled' => true]);
        SiteSetting::forgetCurrent();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function token(array $payload): string
    {
        return Crypt::encryptString(json_encode($payload));
    }

    /**
     * A token sealed with a key this app does not hold — exactly what an
     * attacker who reverse-engineered the payload shape but never obtained
     * APP_KEY would be able to produce.
     */
    private function foreignKeyToken(array $payload): string
    {
        $cipher = config('app.cipher');

        return (new Encrypter(random_bytes(32), $cipher))->encryptString(json_encode($payload));
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    private function validPayload(string $uid = '111'): array
    {
        return ['uid' => $uid, 'exp' => now()->addHours(2)->timestamp];
    }

    public function test_a_token_sealed_with_a_different_app_key_is_rejected(): void
    {
        $this->getJson(route('activity.comble.state'), $this->bearer($this->foreignKeyToken($this->validPayload())))
            ->assertStatus(401);
    }

    /**
     * Reaches the MAC check specifically: the envelope stays well-formed
     * base64 JSON with a valid iv, so decryption gets as far as verifying
     * integrity rather than failing earlier on parsing.
     */
    public function test_a_token_whose_ciphertext_was_tampered_with_is_rejected(): void
    {
        $envelope = json_decode(base64_decode($this->token($this->validPayload())), true);

        $decoded = base64_decode($envelope['value']);
        $decoded[0] = $decoded[0] === 'A' ? 'B' : 'A';
        $envelope['value'] = base64_encode($decoded);

        $tampered = base64_encode(json_encode($envelope));

        $this->getJson(route('activity.comble.state'), $this->bearer($tampered))->assertStatus(401);
    }

    /**
     * The reason VerifyActivityToken checks is_int($expiresAt) rather than
     * just comparing: JSON can carry "exp" as a string, and a loose
     * comparison against a numeric string would happily accept a token that
     * never expires.
     */
    public function test_an_expiry_sent_as_a_numeric_string_is_rejected(): void
    {
        $token = $this->token(['uid' => '111', 'exp' => (string) now()->addHours(2)->timestamp]);

        $this->getJson(route('activity.comble.state'), $this->bearer($token))->assertStatus(401);
    }

    /**
     * Fractional on purpose: an integral float round-trips through
     * json_encode/json_decode back into a PHP int, so it would sail through
     * is_int() and prove nothing about the type check.
     */
    public function test_an_expiry_sent_as_a_float_is_rejected(): void
    {
        $token = $this->token(['uid' => '111', 'exp' => now()->addHours(2)->timestamp + 0.5]);

        $this->getJson(route('activity.comble.state'), $this->bearer($token))->assertStatus(401);
    }

    public function test_a_token_with_no_uid_is_rejected(): void
    {
        $this->getJson(route('activity.comble.state'), $this->bearer($this->token(['exp' => now()->addHour()->timestamp])))
            ->assertStatus(401);
    }

    public function test_a_token_with_an_empty_uid_is_rejected(): void
    {
        $this->getJson(route('activity.comble.state'), $this->bearer($this->token(['uid' => '', 'exp' => now()->addHour()->timestamp])))
            ->assertStatus(401);
    }

    /**
     * Discord snowflakes exceed 32-bit range, so an id that arrives as a JSON
     * number rather than a string is a sign the token wasn't minted by
     * ActivityAuthController (which reads it from Discord's /users/@me as a
     * string) — and it would compare unreliably downstream.
     */
    public function test_a_token_whose_uid_is_an_integer_is_rejected(): void
    {
        $this->getJson(route('activity.comble.state'), $this->bearer($this->token(['uid' => 111, 'exp' => now()->addHour()->timestamp])))
            ->assertStatus(401);
    }

    public function test_a_token_whose_plaintext_is_not_json_is_rejected(): void
    {
        $this->getJson(route('activity.comble.state'), $this->bearer(Crypt::encryptString('hello')))
            ->assertStatus(401);
    }

    public function test_a_non_bearer_authorization_scheme_is_rejected(): void
    {
        $this->getJson(route('activity.comble.state'), [
            'Authorization' => 'Basic '.base64_encode('user:pass'),
        ])->assertStatus(401);
    }

    public function test_an_expired_token_is_rejected_even_by_one_second(): void
    {
        $this->getJson(route('activity.comble.state'), $this->bearer($this->token(['uid' => '111', 'exp' => now()->timestamp - 1])))
            ->assertStatus(401);
    }

    /**
     * The cross-user test. VerifyActivityToken sets the verified id as a
     * *request attribute*, which is server-side only and cannot be written
     * from outside; the controller must read it from there and never from
     * request input. If it ever switched to $request->input('discord_user_id'),
     * anyone holding any valid token could play as — and overwrite the
     * progress of — any Discord user they named.
     */
    public function test_a_body_supplied_discord_user_id_cannot_override_the_token_identity(): void
    {
        $game = Game::create(['name' => 'Test Fighter', 'complete' => 1, 'modPass' => 'x']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 0]);

        Combo::create([
            'combo' => 'AAA BBB CCC DDD EEE',
            'character_idcharacter' => $character->idcharacter,
            'submited' => now(),
            'type' => $type->entryid,
            'damage' => 3000,
        ]);

        $this->postJson(route('activity.comble.guess'), [
            'game_id' => $game->idgame,
            'character_id' => $character->idcharacter,
            'listing_type_id' => $type->entryid,
            'damage' => 3000,
            // All three of the names the identity could plausibly be read
            // from, so a regression to any of them fails this test.
            'discord_user_id' => '999',
            'uid' => '999',
            'user_id' => '999',
        ], $this->bearer($this->token($this->validPayload('111'))))->assertOk();

        $this->assertTrue(
            CombleAttempt::where('visitor_key', 'discord:111')->exists(),
            'The attempt was not recorded against the token identity.'
        );

        $this->assertFalse(
            CombleAttempt::where('visitor_key', 'discord:999')->exists(),
            'A body-supplied Discord user id overrode the verified token identity.'
        );
    }
}
