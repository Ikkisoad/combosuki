<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * VerifyDiscordSignature is the only thing standing between the public
 * internet and the entire bot command surface: routes/discord.php is
 * registered outside the `web` group (see bootstrap/app.php), so those
 * requests carry no session and no CSRF token, and the Ed25519 check is
 * their sole authentication. Getting past it means reaching /csk submit
 * (which creates a real combo under a linked user's name), Comble guesses,
 * and combo verification.
 *
 * DiscordInteractionTest::test_invalid_signature_is_rejected covers the
 * all-zero-signature case. These cover the shapes an attacker actually
 * reaches for — a signature that is genuinely valid, just not for *this*
 * (body, timestamp) pair — plus the inputs that reach sodium_hex2bin's
 * throwing path, which must fail closed as a 401 rather than a 500.
 */
class DiscordSignatureSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();

        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);

        config(['services.discord.public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair))]);

        // The "array" cache store (see phpunit.xml) lives for the whole test
        // process; the interaction controller caches follow-up tokens in it.
        Cache::flush();

        // Anything that gets *past* the middleware sends a private follow-up
        // webhook — see DiscordInteractionTest::setUp().
        Http::fake(['discord.com/*' => Http::response(['id' => 'test-message-id'], 200)]);
    }

    /**
     * Posts a raw body with fully controlled signature headers, so a test can
     * pair a body with a signature that was computed over something else.
     */
    private function postRaw(string $body, string $signature, string $timestamp): TestResponse
    {
        return $this->call('POST', route('discord.interactions'), server: [
            'HTTP_X-Signature-Ed25519' => $signature,
            'HTTP_X-Signature-Timestamp' => $timestamp,
            'CONTENT_TYPE' => 'application/json',
        ], content: $body);
    }

    private function sign(string $body, string $timestamp, ?string $secretKey = null): string
    {
        return sodium_bin2hex(sodium_crypto_sign_detached($timestamp.$body, $secretKey ?? $this->secretKey));
    }

    public function test_a_signature_valid_for_a_different_body_is_rejected(): void
    {
        $timestamp = (string) time();
        $signature = $this->sign(json_encode(['type' => 1]), $timestamp);

        $this->postRaw(json_encode(['type' => 2, 'data' => ['name' => 'csk']]), $signature, $timestamp)
            ->assertStatus(401);
    }

    public function test_a_signature_valid_for_a_different_timestamp_is_rejected(): void
    {
        $body = json_encode(['type' => 1]);
        $timestamp = time();

        $this->postRaw($body, $this->sign($body, (string) $timestamp), (string) ($timestamp + 1))
            ->assertStatus(401);
    }

    public function test_a_signature_from_a_different_keypair_is_rejected(): void
    {
        $body = json_encode(['type' => 1]);
        $timestamp = (string) time();
        $attackerKey = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());

        $this->postRaw($body, $this->sign($body, $timestamp, $attackerKey), $timestamp)
            ->assertStatus(401);
    }

    /**
     * sodium_hex2bin() throws SodiumException on non-hex input. The catch in
     * VerifyDiscordSignature has to turn that into the same 401 as any other
     * bad signature — an uncaught throw would be a 500, which both leaks a
     * stack trace and tells an attacker their input reached the crypto.
     */
    public function test_a_non_hex_signature_is_rejected_rather_than_erroring(): void
    {
        $this->postRaw(json_encode(['type' => 1]), str_repeat('z', 128), (string) time())
            ->assertStatus(401);
    }

    public function test_an_odd_length_hex_signature_is_rejected_rather_than_erroring(): void
    {
        $this->postRaw(json_encode(['type' => 1]), str_repeat('a', 127), (string) time())
            ->assertStatus(401);
    }

    public function test_a_missing_public_key_config_fails_closed(): void
    {
        $body = json_encode(['type' => 1]);
        $timestamp = (string) time();
        $signature = $this->sign($body, $timestamp);

        config(['services.discord.public_key' => null]);

        $this->postRaw($body, $signature, $timestamp)->assertStatus(401);
        $this->assertDatabaseCount('combo', 0);
    }

    public function test_a_malformed_public_key_config_fails_closed(): void
    {
        $body = json_encode(['type' => 1]);
        $timestamp = (string) time();
        $signature = $this->sign($body, $timestamp);

        config(['services.discord.public_key' => 'not-hex']);

        $this->postRaw($body, $signature, $timestamp)->assertStatus(401);
    }

    /**
     * The signature covers timestamp . body, so an attacker can't change
     * either — but without a freshness check, a captured request stays valid
     * forever and can be replayed to re-execute whatever it carried. Discord
     * documents the timestamp check as part of the verification contract for
     * exactly this reason.
     */
    public function test_a_captured_interaction_cannot_be_replayed_after_its_timestamp_has_aged(): void
    {
        $body = json_encode(['type' => 1]);
        $timestamp = (string) (time() - 3600);

        $this->postRaw($body, $this->sign($body, $timestamp), $timestamp)
            ->assertStatus(401);
    }

    /**
     * A non-numeric timestamp must be rejected outright rather than cast:
     * (int) 'abc' is 0, which a naive freshness comparison would read as
     * "1970", and abs()-style windows can be fooled by a far-future value.
     */
    public function test_a_non_numeric_timestamp_is_rejected(): void
    {
        $body = json_encode(['type' => 1]);

        $this->postRaw($body, $this->sign($body, 'abc'), 'abc')->assertStatus(401);
    }

    public function test_a_far_future_timestamp_is_rejected(): void
    {
        $body = json_encode(['type' => 1]);
        $timestamp = (string) (time() + 3600);

        $this->postRaw($body, $this->sign($body, $timestamp), $timestamp)
            ->assertStatus(401);
    }
}
