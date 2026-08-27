<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private GameEntry $listingType;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Test Character', 'game_idgame' => $this->game->idgame]);
        $this->listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $this->game->idgame, 'order' => 1]);

        $this->user = User::create(['nickname' => 'fan', 'password' => 'password123']);
    }

    public function test_profile_shows_an_empty_state_for_a_user_with_no_combos(): void
    {
        $this->get(route('users.show', $this->user))
            ->assertOk()
            ->assertSee('fan')
            ->assertSee('No combos submitted yet.');
    }

    public function test_profile_only_shows_the_owners_combos_ordered_by_views(): void
    {
        $otherUser = User::create(['nickname' => 'other', 'password' => 'password123']);

        Combo::create([
            'combo' => 'Low Views', 'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid, 'user_iduser' => $this->user->iduser,
        ])->increment('views', 5);
        Combo::create([
            'combo' => 'High Views', 'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid, 'user_iduser' => $this->user->iduser,
        ])->increment('views', 50);
        Combo::create([
            'combo' => 'Other Users Combo', 'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid, 'user_iduser' => $otherUser->iduser,
        ])->increment('views', 100);

        // Viewed as the profile owner: an untrusted user's own unverified
        // combos are hidden from other visitors (see ComboVisibilityScopeTest),
        // but this test is about owner-filtering and view-ordering, not
        // verification, so it views its own profile as itself.
        $this->actingAs($this->user);

        $response = $this->get(route('users.show', $this->user))->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('Other Users Combo', $content);
        $this->assertTrue(strpos($content, 'High Views') < strpos($content, 'Low Views'));
    }

    public function test_profile_hides_unverified_combos_from_other_visitors(): void
    {
        Combo::create([
            'combo' => 'Unproven Combo', 'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid, 'user_iduser' => $this->user->iduser,
        ]);

        $stranger = User::create(['nickname' => 'stranger', 'password' => 'password123']);
        $this->actingAs($stranger);

        $this->get(route('users.show', $this->user))
            ->assertOk()
            ->assertDontSee('Unproven Combo');
    }

    public function test_profile_stats_identify_the_most_submitted_game_and_character(): void
    {
        $otherCharacter = Character::create(['name' => 'Other Character', 'game_idgame' => $this->game->idgame]);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGameCharacter = Character::create(['name' => 'Far Character', 'game_idgame' => $otherGame->idgame]);

        // Two combos for $this->character (Test Game), one for $otherCharacter (Test Game), one for $otherGameCharacter (Other Game).
        Combo::create(['combo' => 'A', 'character_idcharacter' => $this->character->idcharacter, 'type' => $this->listingType->entryid, 'user_iduser' => $this->user->iduser]);
        Combo::create(['combo' => 'B', 'character_idcharacter' => $this->character->idcharacter, 'type' => $this->listingType->entryid, 'user_iduser' => $this->user->iduser]);
        Combo::create(['combo' => 'C', 'character_idcharacter' => $otherCharacter->idcharacter, 'type' => $this->listingType->entryid, 'user_iduser' => $this->user->iduser]);
        Combo::create(['combo' => 'D', 'character_idcharacter' => $otherGameCharacter->idcharacter, 'type' => $this->listingType->entryid, 'user_iduser' => $this->user->iduser]);

        $this->get(route('users.show', $this->user))
            ->assertOk()
            ->assertSee('4') // total combos
            ->assertSee($this->character->name)
            ->assertSee($this->game->name);
    }

    public function test_own_profile_shows_a_link_to_the_favorites_guide_once_created(): void
    {
        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $this->character->idcharacter, 'type' => $this->listingType->entryid]);

        $this->actingAs($this->user);

        $this->get(route('users.show', $this->user))->assertOk()->assertSee('favorited any combos yet');

        $this->post(route('favorites.store', $combo))->assertOk();

        $this->get(route('users.show', $this->user))->assertOk()->assertSee('View Favorites');
    }

    public function test_change_password_button_only_shows_on_own_profile(): void
    {
        $this->actingAs($this->user);
        $this->get(route('users.show', $this->user))->assertOk()->assertSee('Change Password');

        $viewer = User::create(['nickname' => 'viewer', 'password' => 'password123']);
        $this->actingAs($viewer);
        $this->get(route('users.show', $this->user))->assertOk()->assertDontSee('Change Password');
    }

    public function test_favorites_card_is_hidden_when_viewing_someone_elses_profile(): void
    {
        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $this->character->idcharacter, 'type' => $this->listingType->entryid]);

        $this->actingAs($this->user);
        $this->post(route('favorites.store', $combo))->assertOk();

        $viewer = User::create(['nickname' => 'viewer', 'password' => 'password123']);
        $this->actingAs($viewer);

        $this->get(route('users.show', $this->user))
            ->assertOk()
            ->assertDontSee('My Favorites')
            ->assertDontSee('View Favorites');
    }
}
