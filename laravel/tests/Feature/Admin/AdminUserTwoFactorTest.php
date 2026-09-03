<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * With no recovery codes (see TwoFactorSetupTest), this admin action is the
 * only way to recover an account whose owner lost their authenticator
 * device — see AdminUserController::disableTwoFactor.
 */
class AdminUserTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_disable_a_users_two_factor_authentication(): void
    {
        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        // two_factor_secret/two_factor_confirmed_at are deliberately not
        // mass-assignable (see User::$fillable) — forceFill() bypasses that
        // the same way LastLoginTrackingTest does for last_login_at.
        $user = User::create(['nickname' => 'locked-out', 'password' => 'password123']);
        $user->forceFill([
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->post(route('admin.users.two-factor.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $fresh = $user->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
    }

    public function test_a_moderator_cannot_disable_a_users_two_factor_authentication(): void
    {
        $moderator = User::create(['nickname' => 'mod', 'password' => 'password123', 'is_moderator' => true]);
        // two_factor_secret/two_factor_confirmed_at are deliberately not
        // mass-assignable (see User::$fillable) — forceFill() bypasses that
        // the same way LastLoginTrackingTest does for last_login_at.
        $user = User::create(['nickname' => 'locked-out', 'password' => 'password123']);
        $user->forceFill([
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($moderator)
            ->post(route('admin.users.two-factor.destroy', $user))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }
}
