<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private User $owner;

    private User $otherUser;

    private User $trustedUser;

    private Combo $combo;

    private GameEntry $listingType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Test Character', 'game_idgame' => $this->game->idgame]);
        $this->listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $this->game->idgame, 'order' => 1]);

        $this->owner = User::create(['nickname' => 'owner', 'password' => 'password123']);
        $this->otherUser = User::create(['nickname' => 'other', 'password' => 'password123']);
        $this->trustedUser = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);

        $this->combo = Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
            'user_iduser' => $this->owner->iduser,
        ]);
    }

    public function test_owner_can_update_and_delete_their_own_combo(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('combos.update', $this->combo), [
            'combo' => 'Edited',
            'character_idcharacter' => $this->character->idcharacter,
            'listingtype' => $this->listingType->entryid,
        ])->assertRedirect(route('combos.show', $this->combo));

        $this->assertSame('Edited', $this->combo->fresh()->combo);

        $this->post(route('combos.destroy', $this->combo))->assertRedirect();
        $this->assertDatabaseMissing('combo', ['idcombo' => $this->combo->idcombo]);
    }

    public function test_non_owner_cannot_update_or_delete_someone_elses_combo(): void
    {
        $this->actingAs($this->otherUser);

        $this->post(route('combos.update', $this->combo), [
            'combo' => 'Hacked',
            'character_idcharacter' => $this->character->idcharacter,
            'listingtype' => $this->listingType->entryid,
        ])->assertRedirect()->assertSessionHas('error');

        $this->post(route('combos.destroy', $this->combo))->assertRedirect()->assertSessionHas('error');

        $this->assertSame('A > B > C', $this->combo->fresh()->combo);
        $this->assertDatabaseHas('combo', ['idcombo' => $this->combo->idcombo]);
    }

    public function test_trusted_user_can_update_and_delete_any_combo(): void
    {
        $this->actingAs($this->trustedUser);

        $this->post(route('combos.update', $this->combo), [
            'combo' => 'Edited by moderator',
            'character_idcharacter' => $this->character->idcharacter,
            'listingtype' => $this->listingType->entryid,
        ])->assertRedirect(route('combos.show', $this->combo));

        $this->assertSame('Edited by moderator', $this->combo->fresh()->combo);

        $this->post(route('combos.destroy', $this->combo))->assertRedirect();
        $this->assertDatabaseMissing('combo', ['idcombo' => $this->combo->idcombo]);
    }

    public function test_no_one_but_trusted_users_can_edit_an_unowned_legacy_combo(): void
    {
        $unowned = Combo::create([
            'combo' => 'Legacy combo',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
        ]);

        $this->actingAs($this->otherUser);

        $this->post(route('combos.update', $unowned), [
            'combo' => 'Hacked',
            'character_idcharacter' => $this->character->idcharacter,
            'listingtype' => $this->listingType->entryid,
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($this->trustedUser);

        $this->post(route('combos.update', $unowned), [
            'combo' => 'Claimed by moderator',
            'character_idcharacter' => $this->character->idcharacter,
            'listingtype' => $this->listingType->entryid,
        ])->assertRedirect(route('combos.show', $unowned));

        $this->assertSame('Claimed by moderator', $unowned->fresh()->combo);
    }
}
