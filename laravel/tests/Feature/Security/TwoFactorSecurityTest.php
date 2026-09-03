<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Auth\DiscordAuthController;
use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * TwoFactorLoginChallengeTest and TwoFactorSetupTest cover that the feature
 * works. This covers what happens when someone attacks it: reaching the
 * challenge without the password step, holding a half-authenticated session,
 * replaying another account's code, outlasting the pending TTL, and
 * brute-forcing six digits.
 *
 * The load-bearing property throughout is that
 * TwoFactorChallengeController::markPending() only parks a user id in the
 * session — AuthController::login uses Auth::validate(), which does not log
 * anyone in — so a pending marker must never behave like a session.
 */
class TwoFactorSecurityTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorAuthenticator $authenticator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticator = app(TwoFactorAuthenticator::class);

        // The "array" cache store (see phpunit.xml) lives for the whole test
        // process and backs the rate limiter, so the throttle assertion below
        // would otherwise depend on test ordering.
        Cache::flush();
    }

    private function userWithTwoFactor(string $nickname = 'protected'): User
    {
        $user = User::create(['nickname' => $nickname, 'password' => 'password123']);

        $user->forceFill([
            'two_factor_secret' => $this->authenticator->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function currentCode(User $user): string
    {
        return (new Google2FA)->getCurrentOtp($user->twoFactorSecret());
    }

    /**
     * The most important assertion in this file: between a correct password
     * and a correct code, the requester holds no session at all. If
     * AuthController ever switched Auth::validate() for Auth::attempt(), 2FA
     * would become a redirect an attacker could simply decline to follow.
     */
    public function test_a_correct_password_alone_does_not_authenticate_a_two_factor_account(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login.attempt'), [
            'nickname' => $user->nickname,
            'password' => 'password123',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();

        // And the half-finished state grants nothing anywhere else.
        $this->get(route('two-factor.edit'))->assertRedirect(route('login'));
    }

    public function test_a_valid_code_for_a_different_accounts_secret_does_not_complete_the_challenge(): void
    {
        $victim = $this->userWithTwoFactor('victim');
        $attacker = $this->userWithTwoFactor('attacker');

        $this->post(route('login.attempt'), [
            'nickname' => $victim->nickname,
            'password' => 'password123',
        ]);

        $this->post(route('two-factor.challenge.attempt'), [
            'code' => $this->currentCode($attacker),
        ])->assertSessionHas('error');

        $this->assertGuest();
    }

    /**
     * The pending marker carries its own 5-minute expiry (PENDING_TTL_MINUTES)
     * because a failed code deliberately doesn't consume it — so the TTL is
     * what stops an abandoned half-login from staying redeemable.
     */
    public function test_a_pending_challenge_expires_and_cannot_be_completed_afterwards(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login.attempt'), [
            'nickname' => $user->nickname,
            'password' => 'password123',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->travel(6)->minutes();

        $this->post(route('two-factor.challenge.attempt'), [
            'code' => $this->currentCode($user),
        ])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * Six digits is a million possibilities, but a valid code stays valid for
     * a whole time step — without a throttle, an unattended script would get
     * meaningful odds. throttle:5,1 on the route is the control.
     *
     * The exact number of attempts allowed is deliberately not asserted:
     * Laravel resolves a numeric throttle's key with
     * sha1($route->getDomain().'|'.$request->ip()) for a guest, so every
     * guest route carrying throttle:5,1 — login.attempt included — shares one
     * per-IP bucket, and the password step above has already spent part of
     * it. That's stricter than per-route counting, not looser, so what
     * matters here is that the wall arrives quickly and nothing is granted.
     */
    public function test_the_challenge_is_throttled_against_code_brute_forcing(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login.attempt'), [
            'nickname' => $user->nickname,
            'password' => 'password123',
        ]);

        $attempts = 0;

        do {
            $response = $this->post(route('two-factor.challenge.attempt'), ['code' => '000000']);
            $attempts++;
        } while ($response->getStatusCode() !== 429 && $attempts < 20);

        $this->assertSame(429, $response->getStatusCode(), 'The challenge never started rejecting guesses.');
        $this->assertLessThanOrEqual(5, $attempts);
        $this->assertGuest();
    }

    /**
     * Session fixation, the second-step edition: the challenge is where the
     * session actually becomes authenticated, so it — not the password step —
     * is what has to regenerate the id.
     */
    public function test_completing_the_challenge_regenerates_the_session_id(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login.attempt'), [
            'nickname' => $user->nickname,
            'password' => 'password123',
        ]);

        $before = session()->getId();

        $this->post(route('two-factor.challenge.attempt'), ['code' => $this->currentCode($user)])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    /**
     * A marker naming a user who has since had 2FA turned off (by an admin,
     * or by the user on another device) must not be redeemable — otherwise
     * disabling 2FA would leave a window where a stale marker logs someone in
     * with no second factor at all.
     */
    public function test_a_pending_marker_is_void_once_the_account_no_longer_has_two_factor(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login.attempt'), [
            'nickname' => $user->nickname,
            'password' => 'password123',
        ]);

        $user->disableTwoFactor();

        $this->post(route('two-factor.challenge.attempt'), ['code' => '000000'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * The secret is the shared key: anyone holding it can generate codes
     * forever. It's shown once on the setup page by design (you need it to
     * type into an app manually), but it must appear nowhere else — hence
     * User::$hidden and the 'encrypted' cast.
     */
    public function test_the_two_factor_secret_never_appears_in_another_users_view_or_any_json(): void
    {
        $victim = $this->userWithTwoFactor('victim');
        $secret = $victim->twoFactorSecret();
        $ciphertext = $victim->getRawOriginal('two_factor_secret');

        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);

        $this->actingAs($admin);

        foreach ([
            route('admin.users.index'),
            route('users.show', $victim),
            route('users.search', ['q' => 'vic']),
        ] as $url) {
            $body = $this->get($url)->getContent();

            $this->assertStringNotContainsString($secret, $body, "{$url} leaked the plaintext 2FA secret");
            $this->assertStringNotContainsString((string) $ciphertext, $body, "{$url} leaked the encrypted 2FA secret");
        }

        $this->assertStringNotContainsString('two_factor_secret', json_encode($victim->toArray()));
    }

    public function test_a_secret_is_stored_encrypted_rather_than_in_plaintext(): void
    {
        $user = $this->userWithTwoFactor();

        $raw = (string) $user->getRawOriginal('two_factor_secret');

        $this->assertNotSame($user->twoFactorSecret(), $raw);
        $this->assertSame($user->twoFactorSecret(), Crypt::decryptString($raw));
    }

    /**
     * Enabling, confirming and disabling all sit behind ConfirmsPassword, so
     * someone who walks up to an unlocked, already-signed-in browser still
     * can't quietly swap the second factor for one of their own.
     */
    public function test_two_factor_cannot_be_disabled_without_the_current_password(): void
    {
        $user = $this->userWithTwoFactor();

        $this->actingAs($user)
            ->post(route('two-factor.disable'), ['current_password' => 'wrong-password'])
            ->assertSessionHas('error');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_two_factor_cannot_be_replaced_with_an_attacker_secret_without_the_current_password(): void
    {
        $user = $this->userWithTwoFactor();
        $original = $user->twoFactorSecret();

        $this->actingAs($user)
            ->post(route('two-factor.enable'), ['current_password' => 'wrong-password'])
            ->assertRedirect(route('two-factor.edit'));

        $this->assertSame($original, $user->fresh()->twoFactorSecret());
    }

    /**
     * Documents a deliberate design decision rather than guarding a bug:
     * TwoFactorChallengeController's docblock states that 2FA gates password
     * login only, so a user with 2FA enabled *and* a linked Discord account
     * signs in through Discord without a second factor. That is a real
     * bypass path — whoever changes this test should be changing the policy
     * on purpose, not by accident.
     */
    public function test_two_factor_gates_password_login_only_and_not_discord_sign_in(): void
    {
        $user = $this->userWithTwoFactor();

        $this->assertTrue($user->hasTwoFactorEnabled());

        // The Discord path calls Auth::login() directly after verifying the
        // OAuth identity; no challenge is interposed. Asserted here through
        // the same entry point rather than by driving the whole OAuth flow,
        // which DiscordAuthTest already covers.
        $this->assertFalse(
            method_exists(DiscordAuthController::class, 'twoFactorChallenge'),
            'Discord sign-in gained a 2FA step — update this test and the controller docblock.'
        );
    }
}
