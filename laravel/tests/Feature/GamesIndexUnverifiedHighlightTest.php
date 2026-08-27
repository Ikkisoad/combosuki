<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamesIndexUnverifiedHighlightTest extends TestCase
{
    use RefreshDatabase;

    private const HIGHLIGHT = 'Has unverified combos';

    private Game $gameWithPending;

    private Game $otherGame;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameWithPending = Game::create(['name' => 'Game With Pending', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Character', 'game_idgame' => $this->gameWithPending->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $this->gameWithPending->idgame, 'order' => 1]);

        $author = User::create(['nickname' => 'author', 'password' => 'password123']);

        Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'user_iduser' => $author->iduser,
        ]);

        $this->otherGame = Game::create(['name' => 'Clean Game', 'complete' => 1, 'modPass' => 'secret']);
        $cleanCharacter = Character::create(['name' => 'Character', 'game_idgame' => $this->otherGame->idgame]);
        GameEntry::create(['title' => 'Combo', 'gameid' => $this->otherGame->idgame, 'order' => 1]);
    }

    public function test_admin_sees_highlight_on_any_game_with_a_pending_combo(): void
    {
        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('games.index'))->assertOk()->assertSee(self::HIGHLIGHT);
    }

    public function test_trusted_user_sees_highlight_on_every_game(): void
    {
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $this->actingAs($trusted);

        $this->get(route('games.index'))->assertOk()->assertSee(self::HIGHLIGHT);
    }

    public function test_moderator_only_sees_highlight_on_their_own_moderated_game(): void
    {
        $moderator = User::create(['nickname' => 'mod', 'password' => 'password123', 'is_moderator' => true]);
        $this->otherGame->moderators()->attach($moderator->iduser);

        $this->actingAs($moderator);

        $this->get(route('games.index'))->assertOk()->assertDontSee(self::HIGHLIGHT);

        $this->gameWithPending->moderators()->attach($moderator->iduser);

        $this->get(route('games.index'))->assertOk()->assertSee(self::HIGHLIGHT);
    }

    public function test_guest_and_untrusted_user_never_see_the_highlight(): void
    {
        $this->get(route('games.index'))->assertOk()->assertDontSee(self::HIGHLIGHT);

        $untrusted = User::create(['nickname' => 'plain', 'password' => 'password123']);
        $this->actingAs($untrusted);
        $this->get(route('games.index'))->assertOk()->assertDontSee(self::HIGHLIGHT);
    }

    public function test_no_highlight_when_game_has_zero_pending_combos(): void
    {
        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $this->actingAs($admin);

        $content = $this->get(route('games.index'))->assertOk()->getContent();

        // One combo is pending across both games (in gameWithPending), so the
        // badge must render exactly once even though admin sees every game.
        $this->assertSame(1, substr_count($content, self::HIGHLIGHT));
    }
}
