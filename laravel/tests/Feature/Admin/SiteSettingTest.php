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

    /**
     * The integration is already live in production with real linked accounts,
     * so the migration's default must not switch it off.
     */
    public function test_the_discord_integration_defaults_to_enabled(): void
    {
        $this->assertTrue(SiteSetting::current()->discord_integration_enabled);
        $this->assertTrue(SiteSetting::discordIntegrationEnabled());
    }

    public function test_current_creates_the_row_without_a_seeder(): void
    {
        $this->assertDatabaseCount('site_setting', 0);

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
            ->assertSee('locks out accounts that only have Discord', false);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status');

        $this->assertFalse(SiteSetting::current()->fresh()->discord_integration_enabled);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), ['discord_integration_enabled' => 1]);

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

    public function test_disabling_the_flag_404s_the_link_actions(): void
    {
        $this->disableDiscord();

        $this->actingAs($this->regular)
            ->post(route('connections.discord.redirect'), ['current_password' => 'password123'])
            ->assertNotFound();

        $this->actingAs($this->regular)
            ->get(route('connections.discord.callback'))
            ->assertNotFound();

        $this->actingAs($this->regular)
            ->post(route('connections.discord.destroy'), ['current_password' => 'password123'])
            ->assertNotFound();
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
}
