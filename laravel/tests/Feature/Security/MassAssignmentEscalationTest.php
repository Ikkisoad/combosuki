<?php

namespace Tests\Feature\Security;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\ListModel;
use App\Models\TierList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every field that decides privilege in this app is mass-assignable:
 * User::$fillable carries is_admin, trusted_user and is_moderator; Combo
 * carries verified, verified_by_iduser and user_iduser; ListModel carries
 * user_iduser and is_favorite_guide; TierList carries user_iduser. Nothing
 * is $guarded anywhere.
 *
 * What actually keeps that safe is a convention rather than a mechanism:
 * every controller builds its create()/update() array by hand instead of
 * passing $request->all() or ->validated() straight through. That convention
 * is invisible — a well-meaning refactor to Model::create($request->
 * validated()) would read like a tidy-up and would turn each of these
 * endpoints into a privilege-escalation primitive.
 *
 * So each test here submits the escalation field as an extra POST key
 * alongside a request that is otherwise entirely valid, and asserts the
 * stored row ignored it.
 */
class MassAssignmentEscalationTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private GameEntry $listingType;

    private User $trusted;

    private User $admin;

    private User $plain;

    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Test Character', 'game_idgame' => $this->game->idgame]);
        $this->listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $this->game->idgame, 'order' => 1]);

        $this->trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $this->admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $this->plain = User::create(['nickname' => 'plain', 'password' => 'password123']);
        $this->victim = User::create(['nickname' => 'victim', 'password' => 'password123']);
    }

    public function test_a_trusted_user_creating_an_account_cannot_grant_it_any_role_flag(): void
    {
        $this->actingAs($this->trusted)->post(route('users.store'), [
            'nickname' => 'newcomer',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_admin' => 1,
            'trusted_user' => 1,
            'is_moderator' => 1,
        ])->assertRedirect(route('users.create'));

        $created = User::where('nickname', 'newcomer')->sole();

        $this->assertFalse((bool) $created->is_admin);
        $this->assertFalse((bool) $created->trusted_user);
        $this->assertFalse((bool) $created->is_moderator);
    }

    /**
     * Admin\UserController::store validates is_admin and trusted_user but
     * never mentions is_moderator — that flag is only meant to move through
     * admin.users.moderator.update, which also assigns moderated games. If it
     * became settable here, a moderator could be created with no games and no
     * audit trail.
     */
    public function test_an_admin_creating_an_account_cannot_grant_the_moderator_flag_through_the_user_form(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'nickname' => 'staffer',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_moderator' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertFalse((bool) User::where('nickname', 'staffer')->sole()->is_moderator);
    }

    public function test_combo_store_ignores_a_submitted_owner_and_verification_state(): void
    {
        $this->actingAs($this->plain)->post(route('games.combos.store', $this->game), [
            'combo' => '5LP > 5MP',
            'character_idcharacter' => $this->character->idcharacter,
            'listingtype' => $this->listingType->entryid,
            'user_iduser' => $this->victim->iduser,
            'verified' => 1,
            'verified_by_iduser' => $this->admin->iduser,
            'verified_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $combo = Combo::sole();

        $this->assertSame($this->plain->iduser, $combo->user_iduser);
        $this->assertFalse((bool) $combo->verified);
        $this->assertNull($combo->verified_by_iduser);
    }

    public function test_combo_update_ignores_a_submitted_owner_and_verification_state(): void
    {
        $combo = Combo::create([
            'combo' => 'A > B',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
            'user_iduser' => $this->plain->iduser,
        ]);

        $this->actingAs($this->plain)->post(route('combos.update', $combo), [
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'listingtype' => $this->listingType->entryid,
            'user_iduser' => $this->victim->iduser,
            'verified' => 1,
            'verified_by_iduser' => $this->admin->iduser,
        ])->assertRedirect(route('combos.show', $combo));

        $combo->refresh();

        $this->assertSame('A > B > C', $combo->combo);
        $this->assertSame($this->plain->iduser, $combo->user_iduser);
        $this->assertFalse((bool) $combo->verified);
        $this->assertNull($combo->verified_by_iduser);
    }

    /**
     * Combo::$fillable also carries two legacy columns from the pre-Laravel
     * app — a free-text author name and a per-combo password — neither of
     * which any form exposes. Editing a combo must not be a way to rewrite
     * attribution.
     */
    public function test_combo_update_cannot_smuggle_a_legacy_author_or_password(): void
    {
        $combo = Combo::create([
            'combo' => 'A > B',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
            'user_iduser' => $this->plain->iduser,
            'author' => 'original',
        ]);

        $this->actingAs($this->plain)->post(route('combos.update', $combo), [
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'listingtype' => $this->listingType->entryid,
            'author' => 'someone else',
            'password' => 'injected',
        ])->assertRedirect();

        $combo->refresh();

        $this->assertSame('original', $combo->author);
        $this->assertNotSame('injected', $combo->password);
    }

    public function test_list_store_ignores_a_submitted_owner_and_favorite_guide_flag(): void
    {
        $this->actingAs($this->plain)->post(route('lists.store'), [
            'list_name' => 'My Guide',
            'game_idgame' => $this->game->idgame,
            'user_iduser' => $this->victim->iduser,
            'is_favorite_guide' => 1,
            'type' => 2,
            'password' => 'injected',
        ])->assertRedirect();

        $list = ListModel::sole();

        $this->assertSame($this->plain->iduser, $list->user_iduser);
        $this->assertFalse((bool) $list->is_favorite_guide);
        $this->assertSame(1, (int) $list->type);
        $this->assertNotSame('injected', $list->password);
    }

    public function test_list_rename_cannot_reassign_ownership(): void
    {
        $list = ListModel::create([
            'list_name' => 'Mine',
            'game_idgame' => $this->game->idgame,
            'user_iduser' => $this->plain->iduser,
            'password' => '',
            'type' => 1,
        ]);

        $this->actingAs($this->plain)->post(route('lists.rename', $list), [
            'list_name' => 'Renamed',
            'user_iduser' => $this->victim->iduser,
            'is_favorite_guide' => 1,
        ])->assertRedirect();

        $list->refresh();

        $this->assertSame('Renamed', $list->list_name);
        $this->assertSame($this->plain->iduser, $list->user_iduser);
        $this->assertFalse((bool) $list->is_favorite_guide);
    }

    /**
     * Backdating and reassigning a tier list is a real admin feature, so the
     * guard is two-layered: StoreTierListRequest::rules() only adds rules for
     * created_at/user_iduser when the requester is_admin (so validated()
     * withholds them otherwise), and TierListController re-checks is_admin
     * before using either. prepareForValidation() merges both keys into the
     * input unconditionally, so neither layer is redundant — this asserts a
     * non-admin gets nowhere even though their input reached the request.
     */
    public function test_a_non_admin_cannot_backdate_or_reassign_a_tier_list(): void
    {
        $this->actingAs($this->plain)->post(route('tier-lists.store'), [
            'title' => 'My Tiers',
            'game_idgame' => $this->game->idgame,
            'entries' => [
                ['character_idcharacter' => $this->character->idcharacter, 'tier' => 'S'],
            ],
            'user_iduser' => $this->victim->iduser,
            'created_at' => '2000-01-01 00:00:00',
        ])->assertRedirect();

        $tierList = TierList::sole();

        $this->assertSame($this->plain->iduser, $tierList->user_iduser);
        $this->assertTrue(
            $tierList->created_at->greaterThan(now()->subMinute()),
            'A non-admin managed to backdate their tier list.'
        );
    }

    /**
     * The positive control for the test above: over-tightening the guard so
     * that admins lose the feature would otherwise look like a pass.
     */
    public function test_an_admin_can_still_reassign_and_backdate_a_tier_list(): void
    {
        $this->actingAs($this->admin)->post(route('tier-lists.store'), [
            'title' => 'Historic Tiers',
            'game_idgame' => $this->game->idgame,
            'entries' => [
                ['character_idcharacter' => $this->character->idcharacter, 'tier' => 'A'],
            ],
            'user_iduser' => $this->victim->iduser,
            'created_at' => '2000-01-01 00:00:00',
        ])->assertRedirect();

        $tierList = TierList::sole();

        $this->assertSame($this->victim->iduser, $tierList->user_iduser);
        $this->assertSame('2000-01-01', $tierList->created_at->toDateString());
    }
}
