<?php

namespace Tests\Feature\Admin;

use App\Models\BotHit;
use App\Models\Character;
use App\Models\Combo;
use App\Models\DiscordCommandUsage;
use App\Models\Game;
use App\Models\ListModel;
use App\Models\TierList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
    }

    public function test_admin_can_view_the_analytics_page_with_totals_and_top_lists(): void
    {
        $game = Game::create(['name' => 'Popular Game', 'complete' => 1, 'modPass' => '', 'views' => 50]);
        $quietGame = Game::create(['name' => 'Quiet Game', 'complete' => 1, 'modPass' => '', 'views' => 5]);

        $character = Character::create(['name' => 'Star Character', 'game_idgame' => $game->idgame, 'views' => 30]);
        $type = \App\Models\GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        Combo::create(['combo' => 'A B C', 'character_idcharacter' => $character->idcharacter, 'type' => $type->entryid, 'views' => 20]);

        ListModel::create(['list_name' => 'Best Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1, 'views' => 15]);
        TierList::create(['title' => 'S Tier List', 'game_idgame' => $game->idgame, 'views' => 10]);

        $this->actingAs($this->admin());

        $response = $this->get(route('admin.analytics'));

        $response->assertOk();
        $response->assertSee('View Analytics');
        $response->assertSeeInOrder(['Popular Game', 'Quiet Game']);
        $response->assertSee('Star Character');
        $response->assertSee('Best Guide');
        $response->assertSee('S Tier List');
        $response->assertSee('50'); // Popular Game's view total
    }

    public function test_admin_sees_top_bot_hit_pages(): void
    {
        BotHit::create(['path' => '/games/1', 'ip_address' => '1.2.3.4', 'user_agent' => 'BadBot', 'created_at' => now()]);
        BotHit::create(['path' => '/games/1', 'ip_address' => '1.2.3.5', 'user_agent' => 'BadBot', 'created_at' => now()]);
        BotHit::create(['path' => '/combos/2', 'ip_address' => '1.2.3.6', 'user_agent' => 'BadBot', 'created_at' => now()]);

        $this->actingAs($this->admin());

        $response = $this->get(route('admin.analytics'));

        $response->assertOk();
        $response->assertSee('Top 10 Pages by Bot Hits');
        $response->assertSee('/games/1');
        $response->assertSee('Bot Traffic');
        $response->assertSee('honeypot hits recorded');
    }

    public function test_admin_sees_top_discord_commands(): void
    {
        DiscordCommandUsage::create(['command' => 'search', 'uses' => 15]);
        DiscordCommandUsage::create(['command' => 'comble', 'uses' => 5]);

        $this->actingAs($this->admin());

        $response = $this->get(route('admin.analytics'));

        $response->assertOk();
        $response->assertSee('Top Discord Commands');
        $response->assertSeeInOrder(['search', 'comble']);
        $response->assertSee('Discord Commands');
        $response->assertSee('invocations recorded');
        $response->assertSee('20');
    }

    public function test_admin_sees_discord_bot_guild_count(): void
    {
        Config::set('services.discord.bot_token', 'fake-bot-token');
        Http::fake([
            'https://discord.com/api/v10/users/@me/guilds*' => Http::response(
                array_fill(0, 42, ['id' => '1', 'name' => 'Guild'])
            ),
        ]);

        $this->actingAs($this->admin());

        $response = $this->get(route('admin.analytics'));

        $response->assertOk();
        $response->assertSee('active in 42 servers');
    }

    public function test_admin_sees_zero_guild_count_when_bot_has_no_servers(): void
    {
        Config::set('services.discord.bot_token', 'fake-bot-token');
        Http::fake([
            'https://discord.com/api/v10/users/@me/guilds*' => Http::response([]),
        ]);

        $this->actingAs($this->admin());

        $response = $this->get(route('admin.analytics'));

        $response->assertOk();
        $response->assertSee('active in 0 servers');
    }

    public function test_analytics_page_omits_guild_count_when_discord_is_unconfigured(): void
    {
        Config::set('services.discord.bot_token', null);

        $this->actingAs($this->admin());

        $response = $this->get(route('admin.analytics'));

        $response->assertOk();
        $response->assertDontSee('active in');
    }

    public function test_non_admin_cannot_view_the_analytics_page(): void
    {
        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123']));

        $this->get(route('admin.analytics'))->assertRedirect();
    }
}
