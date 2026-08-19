<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_game_with_sensible_defaults(): void
    {
        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123', 'is_admin' => false]));

        $response = $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();

        $response->assertRedirect(route('admin.game.edit', $game));
        $this->assertSame(0, $game->complete);
        $this->assertSame('https://example.com/new-fighter.png', $game->image);

        $this->assertSame(16, Button::where('game_idgame', $game->idgame)->count());
        $this->assertSame(1, Character::where('game_idgame', $game->idgame)->where('name', 'Combo Chan')->count());
        $this->assertSame(3, GameEntry::where('gameid', $game->idgame)->count());

        $resource = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Where?')->firstOrFail();
        $this->assertSame(['Midscreen', 'Corner'], $resource->values()->orderBy('order')->pluck('value')->all());
    }

    public function test_game_name_and_image_are_required(): void
    {
        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123', 'is_admin' => false]));

        $response = $this->post(route('games.store'), ['name' => '', 'image' => '']);

        $response->assertSessionHasErrors(['name', 'image']);
        $this->assertSame(0, Game::count());
    }
}
