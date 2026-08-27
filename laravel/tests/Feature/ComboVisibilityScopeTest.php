<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    private GameEntry $listingType;

    protected function setUp(): void
    {
        parent::setUp();

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $this->listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
    }

    private function combo(array $attributes): Combo
    {
        return Combo::create(array_merge([
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
        ], $attributes));
    }

    private function visibleIds(?User $viewer): array
    {
        return Combo::query()->visibleTo($viewer)->pluck('idcombo')->all();
    }

    public function test_guest_submission_is_always_visible(): void
    {
        $guestCombo = $this->combo(['user_iduser' => null]);

        $this->assertContains($guestCombo->idcombo, $this->visibleIds(null));

        $stranger = User::create(['nickname' => 'stranger', 'password' => 'password123']);
        $this->assertContains($guestCombo->idcombo, $this->visibleIds($stranger));
    }

    public function test_untrusted_users_unverified_combo_is_hidden_from_others(): void
    {
        $author = User::create(['nickname' => 'author', 'password' => 'password123']);
        $combo = $this->combo(['user_iduser' => $author->iduser]);

        $this->assertNotContains($combo->idcombo, $this->visibleIds(null));

        $otherUntrusted = User::create(['nickname' => 'other', 'password' => 'password123']);
        $this->assertNotContains($combo->idcombo, $this->visibleIds($otherUntrusted));
    }

    public function test_untrusted_users_unverified_combo_is_visible_to_its_own_author(): void
    {
        $author = User::create(['nickname' => 'author', 'password' => 'password123']);
        $combo = $this->combo(['user_iduser' => $author->iduser]);

        $this->assertContains($combo->idcombo, $this->visibleIds($author));
    }

    public function test_untrusted_users_unverified_combo_is_visible_to_staff(): void
    {
        $author = User::create(['nickname' => 'author', 'password' => 'password123']);
        $combo = $this->combo(['user_iduser' => $author->iduser]);

        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $moderator = User::create(['nickname' => 'mod', 'password' => 'password123', 'is_moderator' => true]);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);

        $this->assertContains($combo->idcombo, $this->visibleIds($admin));
        $this->assertContains($combo->idcombo, $this->visibleIds($moderator));
        $this->assertContains($combo->idcombo, $this->visibleIds($trusted));
    }

    public function test_combo_becomes_visible_once_verified(): void
    {
        $author = User::create(['nickname' => 'author', 'password' => 'password123']);
        $combo = $this->combo(['user_iduser' => $author->iduser, 'verified' => 1]);

        $this->assertContains($combo->idcombo, $this->visibleIds(null));
    }

    public function test_combo_becomes_visible_once_its_author_has_another_verified_combo(): void
    {
        $author = User::create(['nickname' => 'author', 'password' => 'password123']);

        $this->combo(['user_iduser' => $author->iduser, 'verified' => 1]);
        $unverified = $this->combo(['user_iduser' => $author->iduser]);

        $this->assertContains($unverified->idcombo, $this->visibleIds(null));
    }
}
