<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * The login-time TOTP challenge. AuthController::login uses Auth::validate()
 * (not attempt()) so a 2FA account gets no session — and no last_login_at
 * bump, see LastLoginTrackingTest — until this second step actually passes.
 */
class TwoFactorLoginChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function twoFactorUser(string $nickname = 'secured'): array
    {
        $engine = new Google2FA;
        $secret = $engine->generateSecretKey();

        // two_factor_secret/two_factor_confirmed_at are deliberately not
        // mass-assignable (see User::$fillable) — forceFill() around a plain
        // create() bypasses that the same way LastLoginTrackingTest does for
        // last_login_at.
        $user = User::create(['nickname' => $nickname, 'password' => 'password123']);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        return [$user->fresh(), $secret];
    }

    public function test_login_with_a_two_factor_account_redirects_to_the_challenge_instead_of_home(): void
    {
        [$user] = $this->twoFactorUser();

        $this->post(route('login.attempt'), ['nickname' => 'secured', 'password' => 'password123'])
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertFalse(Auth::check());
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_a_valid_code_completes_the_login(): void
    {
        [$user, $secret] = $this->twoFactorUser();
        $this->post(route('login.attempt'), ['nickname' => 'secured', 'password' => 'password123']);

        Carbon::setTestNow('2026-09-03 12:00:00');
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->post(route('two-factor.challenge.attempt'), ['code' => $code])
            ->assertRedirect(route('home'));

        $this->assertTrue(Auth::check());
        $this->assertTrue($user->fresh()->last_login_at->equalTo(Carbon::now()));
    }

    public function test_an_invalid_code_does_not_log_in(): void
    {
        [$user] = $this->twoFactorUser();
        $this->post(route('login.attempt'), ['nickname' => 'secured', 'password' => 'password123']);

        $this->post(route('two-factor.challenge.attempt'), ['code' => '000000'])
            ->assertSessionHas('error');

        $this->assertFalse(Auth::check());
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_the_challenge_page_redirects_to_login_with_no_pending_session(): void
    {
        $this->get(route('two-factor.challenge'))
            ->assertRedirect(route('login'));
    }

    public function test_submitting_a_code_with_no_pending_session_redirects_to_login(): void
    {
        $this->post(route('two-factor.challenge.attempt'), ['code' => '123456'])
            ->assertRedirect(route('login'));

        $this->assertFalse(Auth::check());
    }

    public function test_an_account_without_two_factor_logs_in_directly(): void
    {
        User::create(['nickname' => 'normal', 'password' => 'password123']);

        $this->post(route('login.attempt'), ['nickname' => 'normal', 'password' => 'password123'])
            ->assertRedirect(route('home'));

        $this->assertTrue(Auth::check());
    }

    /**
     * A secret that can no longer be decrypted (e.g. APP_KEY rotated without
     * APP_PREVIOUS_KEYS) must fail the challenge, not 500 it — see
     * User::twoFactorSecret().
     */
    public function test_a_secret_that_cannot_be_decrypted_fails_the_challenge_instead_of_crashing(): void
    {
        [$user] = $this->twoFactorUser();
        DB::table('user')->where('iduser', $user->iduser)->update(['two_factor_secret' => 'not-actually-encrypted']);
        $this->post(route('login.attempt'), ['nickname' => 'secured', 'password' => 'password123']);

        $this->post(route('two-factor.challenge.attempt'), ['code' => '123456'])
            ->assertSessionHas('error');

        $this->assertFalse(Auth::check());
    }

    /**
     * Auth::attempt() used to rehash a stale bcrypt cost as a side effect of
     * logging in; swapping to Auth::validate() (needed so a 2FA account isn't
     * logged in before the challenge) had to keep doing this explicitly — see
     * AuthController::login.
     */
    public function test_a_stale_password_hash_is_rehashed_on_login(): void
    {
        // phpunit.xml pins BCRYPT_ROUNDS=4 for the whole suite, so the hash
        // has to be made at a cost below that to actually be "stale" here.
        config(['hashing.bcrypt.rounds' => 10]);

        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);
        $staleHash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 4]);
        DB::table('user')->where('iduser', $user->iduser)->update(['password' => $staleHash]);
        $this->assertTrue(Hash::needsRehash($staleHash));

        $this->post(route('login.attempt'), ['nickname' => 'normal', 'password' => 'password123'])
            ->assertRedirect(route('home'));

        $this->assertTrue(Auth::check());
        $fresh = $user->fresh()->password;
        $this->assertNotSame($staleHash, $fresh);
        $this->assertTrue(Hash::check('password123', $fresh));
        $this->assertFalse(Hash::needsRehash($fresh));
    }
}
