<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private GameEntry $listingType;

    private User $author;

    private Combo $combo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Test Character', 'game_idgame' => $this->game->idgame]);
        $this->listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $this->game->idgame, 'order' => 1]);

        $this->author = User::create(['nickname' => 'author', 'password' => 'password123']);

        $this->combo = Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
            'user_iduser' => $this->author->iduser,
        ]);
    }

    public function test_trusted_user_can_verify_a_combo(): void
    {
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $this->actingAs($trusted);

        $this->post(route('combos.verify', $this->combo))
            ->assertRedirect()
            ->assertSessionHas('status');

        $fresh = $this->combo->fresh();
        $this->assertSame(1, (int) $fresh->verified);
        $this->assertSame($trusted->iduser, $fresh->verified_by_iduser);
        $this->assertNotNull($fresh->verified_at);
    }

    public function test_moderator_and_admin_can_verify_a_combo(): void
    {
        $moderator = User::create(['nickname' => 'mod', 'password' => 'password123', 'is_moderator' => true]);
        $this->actingAs($moderator);
        $this->post(route('combos.verify', $this->combo))->assertRedirect()->assertSessionHas('status');
        $this->assertSame(1, (int) $this->combo->fresh()->verified);

        $otherCombo = Combo::create([
            'combo' => 'D > E > F',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
            'user_iduser' => $this->author->iduser,
        ]);

        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $this->actingAs($admin);
        $this->post(route('combos.verify', $otherCombo))->assertRedirect()->assertSessionHas('status');
        $this->assertSame(1, (int) $otherCombo->fresh()->verified);
    }

    public function test_untrusted_non_owner_cannot_verify_a_combo(): void
    {
        $stranger = User::create(['nickname' => 'stranger', 'password' => 'password123']);
        $this->actingAs($stranger);

        $this->post(route('combos.verify', $this->combo))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($this->combo->fresh()->verified);
    }

    public function test_untrusted_owner_cannot_verify_their_own_combo(): void
    {
        $this->actingAs($this->author);

        $this->post(route('combos.verify', $this->combo))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($this->combo->fresh()->verified);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('combos.verify', $this->combo))->assertRedirect(route('login'));
    }

    public function test_verify_button_only_shown_to_authorized_users(): void
    {
        $this->actingAs($this->author);
        $this->get(route('combos.show', $this->combo))->assertOk()->assertDontSee('Verify Combo');

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $this->actingAs($trusted);
        $this->get(route('combos.show', $this->combo))->assertOk()->assertSee('Verify Combo');
    }

    public function test_verified_by_row_only_shown_once_verified(): void
    {
        $this->get(route('combos.show', $this->combo))->assertOk()->assertDontSee('Verified by');

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $this->combo->markVerifiedBy($trusted);

        $this->get(route('combos.show', $this->combo))->assertOk()->assertSee('Verified by')->assertSee('trusted');
    }
}
