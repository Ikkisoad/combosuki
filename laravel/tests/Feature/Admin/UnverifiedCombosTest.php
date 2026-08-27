<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnverifiedCombosTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private GameEntry $listingType;

    private User $author;

    private Combo $unverified;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Test Character', 'game_idgame' => $this->game->idgame]);
        $this->listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $this->game->idgame, 'order' => 1]);

        $this->author = User::create(['nickname' => 'author', 'password' => 'password123']);

        $this->unverified = Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
            'user_iduser' => $this->author->iduser,
        ]);

        Combo::create([
            'combo' => 'Already verified',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
            'user_iduser' => $this->author->iduser,
            'verified' => 1,
        ]);

        Combo::create([
            'combo' => 'Guest submission',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
        ]);
    }

    public function test_this_games_moderator_sees_only_that_games_pending_non_guest_combos(): void
    {
        $moderator = User::create(['nickname' => 'mod', 'password' => 'password123', 'is_moderator' => true]);
        $this->game->moderators()->attach($moderator->iduser);

        $this->actingAs($moderator);

        $response = $this->get(route('admin.unverified-combos.index', $this->game))->assertOk();
        $response->assertSee('A &gt; B &gt; C', false);
        $response->assertDontSee('Already verified');
        $response->assertDontSee('Guest submission');
    }

    public function test_admin_can_view_any_games_tab(): void
    {
        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('admin.unverified-combos.index', $this->game))->assertOk();
    }

    public function test_non_moderator_is_denied_access_to_the_tab(): void
    {
        $stranger = User::create(['nickname' => 'stranger', 'password' => 'password123']);
        $this->actingAs($stranger);

        $this->get(route('admin.unverified-combos.index', $this->game))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_verifying_from_the_tab_redirects_back_to_the_tab(): void
    {
        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $this->actingAs($admin);

        $tabUrl = route('admin.unverified-combos.index', $this->game);

        $this->from($tabUrl)
            ->post(route('combos.verify', $this->unverified))
            ->assertRedirect($tabUrl);

        $this->assertSame(1, (int) $this->unverified->fresh()->verified);
    }

    public function test_per_game_moderator_without_global_trust_sees_tab_but_no_verify_button(): void
    {
        $gameOnlyModerator = User::create(['nickname' => 'gamemod', 'password' => 'password123']);
        $this->game->moderators()->attach($gameOnlyModerator->iduser);

        $this->actingAs($gameOnlyModerator);

        $this->get(route('admin.unverified-combos.index', $this->game))
            ->assertOk()
            ->assertDontSee('Verify');

        $this->post(route('combos.verify', $this->unverified))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($this->unverified->fresh()->verified);
    }
}
