<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Passwordless accounts — the state a Discord registration leaves behind.
 *
 * Three rules hold this together: password login must refuse such an account
 * without revealing that it is Discord-only, the owner must be able to set a
 * first password without confirming one that doesn't exist, and Discord must
 * not be disconnectable while it is the only way in.
 */
class AuthPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function passwordlessUser(string $nickname = 'discordonly'): User
    {
        $user = User::create(['nickname' => $nickname, 'password' => 'placeholder']);

        // Query builder bypasses the `hashed` cast, which is exactly how a
        // genuinely null password gets there.
        DB::table('user')->where('iduser', $user->iduser)->update(['password' => null]);

        return $user->fresh();
    }

    // ---------------------------------------------------------------- login

    public function test_password_login_is_refused_for_an_account_with_no_password(): void
    {
        $this->passwordlessUser();

        $this->post(route('login.attempt'), ['nickname' => 'discordonly', 'password' => 'anything'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Incorrect nickname or password.');

        $this->assertFalse(Auth::check());
    }

    /**
     * The refusal must be indistinguishable from a wrong password on a normal
     * account, otherwise the login form becomes a "which accounts are
     * Discord-only?" oracle.
     */
    public function test_the_refusal_is_indistinguishable_from_a_wrong_password(): void
    {
        $this->passwordlessUser();
        User::create(['nickname' => 'normal', 'password' => 'password123']);

        $passwordless = $this->post(route('login.attempt'), ['nickname' => 'discordonly', 'password' => 'x']);
        $wrongPassword = $this->post(route('login.attempt'), ['nickname' => 'normal', 'password' => 'x']);
        $noSuchUser = $this->post(route('login.attempt'), ['nickname' => 'ghost', 'password' => 'x']);

        $this->assertSame('Incorrect nickname or password.', $passwordless->getSession()->get('error'));
        $this->assertSame('Incorrect nickname or password.', $wrongPassword->getSession()->get('error'));
        $this->assertSame('Incorrect nickname or password.', $noSuchUser->getSession()->get('error'));
    }

    /** An empty-string password must be treated the same as null. */
    public function test_an_empty_password_string_cannot_be_logged_into(): void
    {
        $user = User::create(['nickname' => 'blankpass', 'password' => 'placeholder']);
        DB::table('user')->where('iduser', $user->iduser)->update(['password' => '']);

        // A real password is submitted — an empty one is already stopped by
        // validation, which would make this pass for the wrong reason.
        $this->post(route('login.attempt'), ['nickname' => 'blankpass', 'password' => 'anything'])
            ->assertSessionHas('error', 'Incorrect nickname or password.');

        $this->assertFalse(Auth::check());
    }

    public function test_a_normal_account_still_logs_in(): void
    {
        User::create(['nickname' => 'normal', 'password' => 'password123']);

        $this->post(route('login.attempt'), ['nickname' => 'normal', 'password' => 'password123'])
            ->assertRedirect(route('home'));

        $this->assertTrue(Auth::check());
    }

    // -------------------------------------------------------- setting first

    public function test_a_passwordless_user_is_shown_the_set_password_form(): void
    {
        $this->actingAs($this->passwordlessUser())
            ->get(route('password.edit'))
            ->assertOk()
            ->assertSee('Set a Password')
            ->assertDontSee('Current Password');
    }

    public function test_a_passwordless_user_can_set_a_first_password_without_confirming_one(): void
    {
        $user = $this->passwordlessUser();

        $this->actingAs($user)
            ->post(route('password.update'), [
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHas('status', 'Password set.');

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_the_new_password_then_works_for_login(): void
    {
        $user = $this->passwordlessUser();

        $this->actingAs($user)->post(route('password.update'), [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        Auth::logout();

        $this->post(route('login.attempt'), ['nickname' => 'discordonly', 'password' => 'brand-new-password'])
            ->assertRedirect(route('home'));

        $this->assertTrue(Auth::check());
    }

    // ------------------------------------------------------------- changing

    public function test_a_user_with_a_password_still_must_supply_the_current_one(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);

        $this->actingAs($user)
            ->get(route('password.edit'))
            ->assertOk()
            ->assertSee('Change Password')
            ->assertSee('Current Password');

        $this->actingAs($user)
            ->post(route('password.update'), [
                'password' => 'new-password-here',
                'password_confirmation' => 'new-password-here',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    /**
     * The branch is chosen from the database, never from request input — a
     * forged field must not skip the current-password check.
     */
    public function test_request_input_cannot_skip_the_current_password_check(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);

        $this->actingAs($user)
            ->post(route('password.update'), [
                'hasPassword' => 0,
                'settingFirstPassword' => 1,
                'current_password' => 'wrong-password',
                'password' => 'new-password-here',
                'password_confirmation' => 'new-password-here',
            ])
            ->assertSessionHas('error', 'Incorrect password.');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    // ------------------------------------------------------------- unlinking

    public function test_discord_cannot_be_disconnected_while_it_is_the_only_credential(): void
    {
        $user = $this->passwordlessUser();
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'discorduser',
            'user_iduser' => $user->iduser,
        ]);

        $this->actingAs($user)
            ->post(route('connections.discord.destroy'), ['current_password' => ''])
            ->assertRedirect()
            ->assertSessionHas('error', 'Set a password first — Discord is currently the only way into your account.');

        $this->assertDatabaseCount('user_connected_account', 1);
    }

    public function test_the_connections_page_points_a_passwordless_user_at_setting_one(): void
    {
        $user = $this->passwordlessUser();
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'discorduser',
            'user_iduser' => $user->iduser,
        ]);

        $this->actingAs($user)
            ->get(route('connections.edit'))
            ->assertOk()
            ->assertSee('only way into your account')
            ->assertSee(route('password.edit'), false);
    }

    public function test_disconnecting_works_once_a_password_exists(): void
    {
        $user = $this->passwordlessUser();
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'discorduser',
            'user_iduser' => $user->iduser,
        ]);

        $this->actingAs($user)->post(route('password.update'), [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $this->actingAs($user->fresh())
            ->post(route('connections.discord.destroy'), ['current_password' => 'brand-new-password'])
            ->assertSessionHas('status');

        $this->assertDatabaseCount('user_connected_account', 0);
    }
}
