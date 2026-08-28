<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UserConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regular;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['nickname' => 'boss', 'password' => 'password123', 'is_admin' => true]);
        $this->regular = User::create(['nickname' => 'fan', 'password' => 'password123']);
    }

    private function disableDiscord(): void
    {
        SiteSetting::current()->update(['discord_integration_enabled' => false]);
        SiteSetting::forgetCurrent();
    }

    private function enableActivity(): void
    {
        SiteSetting::current()->update(['discord_activity_enabled' => true]);
        SiteSetting::forgetCurrent();
    }

    /**
     * The integration is already live in production with real linked accounts,
     * so the migration's default must not switch it off.
     */
    public function test_the_discord_integration_defaults_to_enabled(): void
    {
        $this->assertTrue(SiteSetting::current()->discord_integration_enabled);
        $this->assertTrue(SiteSetting::discordIntegrationEnabled());
    }

    /**
     * Unlike discord_integration_enabled, the Activity is brand new (not
     * already live in production), so its migration must not switch it on.
     */
    public function test_the_discord_activity_defaults_to_disabled(): void
    {
        $this->assertFalse(SiteSetting::current()->discord_activity_enabled);
        $this->assertFalse(SiteSetting::discordActivityEnabled());
    }

    /**
     * The migration seeds the single row itself (rather than leaving
     * current()'s firstOrCreate([]) to create it lazily) so two concurrent
     * first-ever requests can't both insert and leave two rows behind.
     */
    public function test_the_row_is_seeded_by_the_migration(): void
    {
        $this->assertDatabaseCount('site_setting', 1);

        SiteSetting::current();

        $this->assertDatabaseCount('site_setting', 1);
    }

    public function test_guests_and_regular_users_cannot_reach_the_settings_page(): void
    {
        $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));

        $this->actingAs($this->regular)
            ->get(route('admin.settings.edit'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->regular)
            ->post(route('admin.settings.update'), ['discord_integration_enabled' => 0])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue(SiteSetting::current()->fresh()->discord_integration_enabled);
    }

    public function test_an_admin_can_see_and_toggle_the_flag(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Enable Discord integration')
            // The lockout consequence has to be on the screen, not just in a doc.
            ->assertSee('locks out accounts that only have Discord', false)
            ->assertSee('Enable the Comble Discord Activity');

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status');

        $this->assertFalse(SiteSetting::current()->fresh()->discord_integration_enabled);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), ['discord_integration_enabled' => 1]);

        $this->assertTrue(SiteSetting::current()->fresh()->discord_integration_enabled);
    }

    /** Independent from discord_integration_enabled: toggling it doesn't touch the other flag. */
    public function test_an_admin_can_toggle_the_activity_flag_independently(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), ['discord_integration_enabled' => 1, 'discord_activity_enabled' => 1]);

        $this->assertTrue(SiteSetting::current()->fresh()->discord_activity_enabled);
        $this->assertTrue(SiteSetting::current()->fresh()->discord_integration_enabled);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), ['discord_integration_enabled' => 1]);

        $this->assertFalse(SiteSetting::current()->fresh()->discord_activity_enabled);
        $this->assertTrue(SiteSetting::current()->fresh()->discord_integration_enabled);
    }

    public function test_the_dashboard_links_to_the_settings_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.settings.edit'), false);
    }

    // ------------------------------------------------- effect of the flag

    public function test_disabling_the_flag_404s_the_connect_actions(): void
    {
        $this->disableDiscord();

        $this->actingAs($this->regular)
            ->post(route('connections.discord.redirect'), ['current_password' => 'password123'])
            ->assertNotFound();

        $this->actingAs($this->regular)
            ->get(route('connections.discord.callback'))
            ->assertNotFound();
    }

    /**
     * The kill switch is meant to stop new sign-ins, not trap someone into
     * keeping a connection they no longer want — disconnecting has to keep
     * working even while the flag is off.
     */
    public function test_disabling_the_flag_does_not_block_disconnecting(): void
    {
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'fanuser',
            'user_iduser' => $this->regular->iduser,
        ]);

        $this->disableDiscord();

        $this->actingAs($this->regular)
            ->post(route('connections.discord.destroy'), ['current_password' => 'password123'])
            ->assertRedirect(route('connections.edit'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    /**
     * The connections page itself is deliberately NOT behind the flag: someone
     * who already linked has to be able to see that the link still exists.
     */
    public function test_the_connections_page_still_opens_with_a_notice_when_disabled(): void
    {
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'fanuser',
            'user_iduser' => $this->regular->iduser,
        ]);

        $this->disableDiscord();

        $this->actingAs($this->regular)
            ->get(route('connections.edit'))
            ->assertOk()
            ->assertSee('currently unavailable')
            // The existing connection is still shown, and still not deleted.
            ->assertSee('fanuser');

        $this->assertDatabaseCount('user_connected_account', 1);
    }

    public function test_disabling_the_flag_does_not_touch_the_discord_bot(): void
    {
        $this->disableDiscord();

        // 401 from VerifyDiscordSignature, NOT 404 from the flag middleware:
        // the request reached the bot's own signature check, which is the
        // proof that the kill switch left routes/discord.php alone. A 404
        // here would mean the flag had swallowed the interactions endpoint
        // and Discord's URL verification would start failing in the portal.
        $this->postJson(route('discord.interactions'), ['type' => 1])
            ->assertStatus(401);
    }

    /** The Activity defaults off (see test_the_discord_activity_defaults_to_disabled), so its route 404s until explicitly enabled. */
    public function test_the_activity_404s_while_its_own_flag_is_off(): void
    {
        $this->get(route('activity.comble.show'))->assertNotFound();
    }

    /** discord_integration_enabled still gates the Activity too — both flags have to be on. */
    public function test_the_activity_404s_when_the_master_discord_flag_is_off_even_if_its_own_flag_is_on(): void
    {
        $this->enableActivity();
        $this->disableDiscord();

        $this->get(route('activity.comble.show'))->assertNotFound();
    }

    public function test_the_activity_is_reachable_once_both_flags_are_on(): void
    {
        $this->enableActivity();

        $this->get(route('activity.comble.show'))->assertOk();
    }

    /** Turning the Activity off on its own must not affect Discord sign-in/linking or the bot. */
    public function test_disabling_only_the_activity_flag_does_not_touch_discord_sign_in_or_the_bot(): void
    {
        $this->enableActivity();
        SiteSetting::current()->update(['discord_activity_enabled' => false]);
        SiteSetting::forgetCurrent();

        $this->post(route('auth.discord.redirect'))->assertRedirectContains('discord.com');

        $this->postJson(route('discord.interactions'), ['type' => 1])->assertStatus(401);
    }
}
