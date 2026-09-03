<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Enabling/disabling TOTP two-factor authentication from /account/two-factor.
 * A secret isn't "enabled" until confirm() succeeds — see
 * User::hasTwoFactorEnabled() — so setup can be started, abandoned, and
 * restarted without ever gating login.
 */
class TwoFactorSetupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string} the confirmed user and its secret
     */
    private function enabledUser(string $nickname = 'normal'): array
    {
        $user = User::create(['nickname' => $nickname, 'password' => 'password123']);
        $this->actingAs($user)->post(route('two-factor.enable'), ['current_password' => 'password123']);
        $secret = $user->fresh()->two_factor_secret;

        $this->actingAs($user->fresh())
            ->post(route('two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)]);

        return [$user->fresh(), $secret];
    }

    public function test_enabling_requires_the_correct_current_password(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);

        $this->actingAs($user)
            ->post(route('two-factor.enable'), ['current_password' => 'wrong'])
            ->assertSessionHas('error', 'Incorrect password.');

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_enabling_generates_a_pending_secret(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);

        $this->actingAs($user)
            ->post(route('two-factor.enable'), ['current_password' => 'password123'])
            ->assertRedirect(route('two-factor.edit'));

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->two_factor_secret);
        $this->assertFalse($fresh->hasTwoFactorEnabled());
    }

    public function test_the_edit_page_shows_a_qr_code_while_pending(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);
        $this->actingAs($user)->post(route('two-factor.enable'), ['current_password' => 'password123']);

        $this->actingAs($user->fresh())
            ->get(route('two-factor.edit'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee($user->fresh()->two_factor_secret);
    }

    public function test_confirming_with_a_valid_code_enables_it(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);
        $this->actingAs($user)->post(route('two-factor.enable'), ['current_password' => 'password123']);
        $secret = $user->fresh()->two_factor_secret;

        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->actingAs($user->fresh())
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect(route('two-factor.edit'))
            ->assertSessionHas('status', 'Two-factor authentication enabled.');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirming_with_an_invalid_code_leaves_it_pending(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);
        $this->actingAs($user)->post(route('two-factor.enable'), ['current_password' => 'password123']);

        $this->actingAs($user->fresh())
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHas('error');

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->two_factor_secret);
        $this->assertFalse($fresh->hasTwoFactorEnabled());
    }

    /**
     * Without this guard, a double-submitted/reloaded enable form on an
     * already-enabled account would silently swap the confirmed secret for a
     * new, unscanned one — see TwoFactorController::store().
     */
    public function test_enabling_again_while_already_enabled_does_not_replace_the_secret(): void
    {
        [$user, $originalSecret] = $this->enabledUser();

        $this->actingAs($user)
            ->post(route('two-factor.enable'), ['current_password' => 'password123'])
            ->assertRedirect(route('two-factor.edit'));

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasTwoFactorEnabled());
        $this->assertSame($originalSecret, $fresh->two_factor_secret);
    }

    public function test_disabling_requires_the_correct_current_password(): void
    {
        [$user] = $this->enabledUser();

        $this->actingAs($user)
            ->post(route('two-factor.disable'), ['current_password' => 'wrong'])
            ->assertSessionHas('error', 'Incorrect password.');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disabling_clears_both_columns(): void
    {
        [$user] = $this->enabledUser();

        $this->actingAs($user)
            ->post(route('two-factor.disable'), ['current_password' => 'password123'])
            ->assertSessionHas('status', 'Two-factor authentication disabled.');

        $fresh = $user->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
    }

    /**
     * A secret that can no longer be decrypted (e.g. APP_KEY rotated without
     * APP_PREVIOUS_KEYS) must not 500 the settings page — User::twoFactorSecret()
     * treats it the same as no secret, so the page falls back to "not
     * enabled" and the user can simply start over.
     */
    public function test_the_edit_page_does_not_crash_when_the_pending_secret_cannot_be_decrypted(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);
        DB::table('user')->where('iduser', $user->iduser)->update(['two_factor_secret' => 'not-actually-encrypted']);

        $this->actingAs($user->fresh())
            ->get(route('two-factor.edit'))
            ->assertOk()
            ->assertSee('Not enabled');
    }

    public function test_confirming_is_refused_when_the_secret_cannot_be_decrypted(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);
        DB::table('user')->where('iduser', $user->iduser)->update(['two_factor_secret' => 'not-actually-encrypted']);

        $this->actingAs($user->fresh())
            ->post(route('two-factor.confirm'), ['code' => '123456'])
            ->assertRedirect(route('two-factor.edit'));

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }
}
