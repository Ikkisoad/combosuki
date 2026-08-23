<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Game;
use App\Models\TierList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierListAdminOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeGameWithCharacter(): array
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        return [$game, $character];
    }

    public function test_create_form_shows_override_fields_only_to_admins(): void
    {
        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $user = User::create(['nickname' => 'regular', 'password' => 'password123']);

        $this->actingAs($admin);
        $adminResponse = $this->get(route('tier-lists.create'));
        $adminResponse->assertOk();
        $adminResponse->assertSee('name="created_at"', false);
        $adminResponse->assertSee('name="user_iduser"', false);

        $this->actingAs($user);
        $userResponse = $this->get(route('tier-lists.create'));
        $userResponse->assertOk();
        $userResponse->assertDontSee('name="created_at"', false);
        $userResponse->assertDontSee('name="user_iduser"', false);
    }

    public function test_admin_can_backdate_a_tier_list_and_assign_it_to_another_user(): void
    {
        [$game, $character] = $this->makeGameWithCharacter();

        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $otherUser = User::create(['nickname' => 'someone-else', 'password' => 'password123']);

        $this->actingAs($admin);

        $response = $this->post(route('tier-lists.store'), [
            'title' => 'Backdated List',
            'game_idgame' => $game->idgame,
            'created_at' => '2020-01-15',
            'user_iduser' => $otherUser->iduser,
            'entries' => [
                ['character_idcharacter' => $character->idcharacter, 'tier' => 'S'],
            ],
        ]);

        $tierList = TierList::sole();

        $response->assertRedirect(route('tier-lists.show', $tierList));
        $this->assertSame($otherUser->iduser, $tierList->user_iduser);
        $this->assertSame('2020-01-15', $tierList->created_at->toDateString());
    }

    public function test_admin_can_mark_a_tier_list_as_anonymous(): void
    {
        [$game, $character] = $this->makeGameWithCharacter();

        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);

        $this->actingAs($admin);

        $this->post(route('tier-lists.store'), [
            'title' => 'Anonymous List',
            'game_idgame' => $game->idgame,
            'user_iduser' => '',
            'entries' => [
                ['character_idcharacter' => $character->idcharacter, 'tier' => 'A'],
            ],
        ]);

        $tierList = TierList::sole();

        $this->assertNull($tierList->user_iduser);
    }

    public function test_non_admin_cannot_override_the_author_or_created_date(): void
    {
        [$game, $character] = $this->makeGameWithCharacter();

        $user = User::create(['nickname' => 'regular', 'password' => 'password123']);
        $otherUser = User::create(['nickname' => 'someone-else', 'password' => 'password123']);

        $this->actingAs($user);

        $this->post(route('tier-lists.store'), [
            'title' => 'Regular List',
            'game_idgame' => $game->idgame,
            'created_at' => '2020-01-15',
            'user_iduser' => $otherUser->iduser,
            'entries' => [
                ['character_idcharacter' => $character->idcharacter, 'tier' => 'B'],
            ],
        ]);

        $tierList = TierList::sole();

        $this->assertSame($user->iduser, $tierList->user_iduser);
        $this->assertTrue($tierList->created_at->greaterThan(now()->subMinute()));
    }
}
