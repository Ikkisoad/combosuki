<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $characterOne;

    private Character $characterTwo;

    private User $owner;

    private User $otherUser;

    private User $trustedUser;

    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => true]);
        $this->characterOne = Character::create(['name' => 'Ryu', 'game_idgame' => $this->game->idgame]);
        $this->characterTwo = Character::create(['name' => 'Ken', 'game_idgame' => $this->game->idgame]);

        $this->owner = User::create(['nickname' => 'owner', 'password' => 'password123']);
        $this->otherUser = User::create(['nickname' => 'other', 'password' => 'password123']);
        $this->trustedUser = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);

        $this->match = GameMatch::create([
            'game_idgame' => $this->game->idgame,
            'player_one' => 'Alice',
            'player_one_character_idcharacter' => $this->characterOne->idcharacter,
            'player_two' => 'Bob',
            'player_two_character_idcharacter' => $this->characterTwo->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now(),
            'user_iduser' => $this->owner->iduser,
        ]);
    }

    private function updatePayload(): array
    {
        return [
            'player_one' => 'Alice Updated',
            'player_one_character_idcharacter' => $this->characterOne->idcharacter,
            'player_two' => 'Bob',
            'player_two_character_idcharacter' => $this->characterTwo->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now()->toDateString(),
        ];
    }

    public function test_owner_can_update_and_delete_their_own_match(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('matches.update', $this->match), $this->updatePayload())
            ->assertRedirect(route('games.matches.index', $this->game));

        $this->assertSame('Alice Updated', $this->match->fresh()->player_one);

        $this->post(route('matches.destroy', $this->match))->assertRedirect();
        $this->assertDatabaseMissing('matches', ['idmatch' => $this->match->idmatch]);
    }

    public function test_non_owner_cannot_update_or_delete_someone_elses_match(): void
    {
        $this->actingAs($this->otherUser);

        $this->post(route('matches.update', $this->match), $this->updatePayload())
            ->assertRedirect()->assertSessionHas('error');

        $this->post(route('matches.destroy', $this->match))->assertRedirect()->assertSessionHas('error');

        $this->assertSame('Alice', $this->match->fresh()->player_one);
        $this->assertDatabaseHas('matches', ['idmatch' => $this->match->idmatch]);
    }

    public function test_trusted_user_can_update_and_delete_any_match(): void
    {
        $this->actingAs($this->trustedUser);

        $this->post(route('matches.update', $this->match), $this->updatePayload())
            ->assertRedirect(route('games.matches.index', $this->game));

        $this->assertSame('Alice Updated', $this->match->fresh()->player_one);

        $this->post(route('matches.destroy', $this->match))->assertRedirect();
        $this->assertDatabaseMissing('matches', ['idmatch' => $this->match->idmatch]);
    }

    public function test_no_one_but_trusted_users_can_edit_an_unowned_legacy_match(): void
    {
        $unowned = GameMatch::create([
            'game_idgame' => $this->game->idgame,
            'player_one' => 'Legacy',
            'player_one_character_idcharacter' => $this->characterOne->idcharacter,
            'player_two' => 'Player',
            'player_two_character_idcharacter' => $this->characterTwo->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now(),
        ]);

        $this->actingAs($this->otherUser);

        $this->post(route('matches.update', $unowned), $this->updatePayload())
            ->assertRedirect()->assertSessionHas('error');

        $this->actingAs($this->trustedUser);

        $this->post(route('matches.update', $unowned), $this->updatePayload())
            ->assertRedirect(route('games.matches.index', $this->game));

        $this->assertSame('Alice Updated', $unowned->fresh()->player_one);
    }
}
