<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSecondaryResourceCharactersTest extends TestCase
{
    use RefreshDatabase;

    private function createGameAsModerator(): Game
    {
        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        return Game::where('name', 'New Fighter')->firstOrFail();
    }

    public function test_moderator_can_link_characters_to_a_secondary_resource(): void
    {
        $this->actingAs(User::create(['nickname' => 'moderator', 'password' => 'password123', 'trusted_user' => true]));

        $game = $this->createGameAsModerator();
        $characterA = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $characterB = Character::create(['name' => 'Second Fighter', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Assist',
            'type' => 1,
            'primaryORsecundary' => 0,
        ]);

        $response = $this->post(route('admin.resources.store', $game), [
            'action' => 'SaveAll',
            'resources' => [
                $resource->idgame_resources => [
                    'resource' => $resource->text_name,
                    'type' => $resource->type,
                    'primaryORsecundary' => 0,
                    'characters' => [$characterA->idcharacter],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.resources.index', $game));
        $this->assertSame([$characterA->idcharacter], $resource->fresh()->characters->pluck('idcharacter')->all());

        // Submitting again without `characters` clears the links back to unrestricted.
        $this->post(route('admin.resources.store', $game), [
            'action' => 'SaveAll',
            'resources' => [
                $resource->idgame_resources => [
                    'resource' => $resource->text_name,
                    'type' => $resource->type,
                    'primaryORsecundary' => 0,
                ],
            ],
        ]);

        $this->assertSame([], $resource->fresh()->characters->pluck('idcharacter')->all());
    }

    public function test_non_moderator_cannot_manage_a_games_resources(): void
    {
        $this->actingAs(User::create(['nickname' => 'owner', 'password' => 'password123', 'trusted_user' => true]));
        $game = $this->createGameAsModerator();

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Assist',
            'type' => 1,
            'primaryORsecundary' => 0,
        ]);

        $this->actingAs(User::create(['nickname' => 'outsider', 'password' => 'password123', 'trusted_user' => true]));

        $response = $this->post(route('admin.resources.store', $game), [
            'action' => 'SaveAll',
            'resources' => [
                $resource->idgame_resources => [
                    'resource' => 'Assist',
                    'type' => 1,
                    'primaryORsecundary' => 0,
                    'characters' => [],
                ],
            ],
        ]);

        $response->assertRedirect()->assertSessionHas('error');
    }
}
