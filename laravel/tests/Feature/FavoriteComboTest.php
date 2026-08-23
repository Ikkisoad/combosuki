<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteComboTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Combo $combo;

    private Combo $otherGameCombo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['nickname' => 'fan', 'password' => 'password123']);

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);

        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherCharacter = Character::create(['name' => 'Other Character', 'game_idgame' => $otherGame->idgame]);

        $this->combo = Combo::create([
            'combo' => '5A 5B 5C',
            'character_idcharacter' => $character->idcharacter,
            'type' => 1,
        ]);

        $this->otherGameCombo = Combo::create([
            'combo' => '2A 2B 2C',
            'character_idcharacter' => $otherCharacter->idcharacter,
            'type' => 1,
        ]);
    }

    public function test_favoriting_a_combo_creates_a_guide_and_attaches_it(): void
    {
        $this->actingAs($this->user);

        $this->post(route('favorites.store', $this->combo))->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('list', [
            'user_iduser' => $this->user->iduser,
            'list_name' => 'Favorites',
            'game_idgame' => null,
            'is_favorite_guide' => 1,
        ]);

        $guide = $this->user->favoriteGuide()->first();
        $this->assertNotNull($guide);
        $this->assertTrue($guide->combos->contains('idcombo', $this->combo->idcombo));
    }

    public function test_favoriting_combos_from_different_games_reuses_the_same_guide(): void
    {
        $this->actingAs($this->user);

        $this->post(route('favorites.store', $this->combo))->assertOk();
        $this->post(route('favorites.store', $this->otherGameCombo))->assertOk();

        $this->assertSame(1, ListModel::where('user_iduser', $this->user->iduser)->where('is_favorite_guide', true)->count());

        $guide = $this->user->favoriteGuide()->first();
        $this->assertCount(2, $guide->combos);
    }

    public function test_unfavoriting_detaches_the_combo_but_keeps_the_guide(): void
    {
        $this->actingAs($this->user);

        $this->post(route('favorites.store', $this->combo))->assertOk();
        $this->post(route('favorites.destroy', $this->combo))->assertOk()->assertJson(['status' => 'ok']);

        $guide = $this->user->favoriteGuide()->first();
        $this->assertNotNull($guide);
        $this->assertFalse($guide->combos->contains('idcombo', $this->combo->idcombo));
    }

    public function test_guest_cannot_favorite_a_combo(): void
    {
        $this->post(route('favorites.store', $this->combo))->assertRedirect(route('login'));

        $this->assertDatabaseMissing('list', ['is_favorite_guide' => 1]);
    }

    public function test_favorite_guide_is_excluded_from_lists_index_and_search(): void
    {
        $this->actingAs($this->user);
        $this->post(route('favorites.store', $this->combo))->assertOk();

        $this->get(route('lists.index'))->assertOk()->assertDontSee('Favorites');
        $this->get(route('lists.search', ['list_name' => 'Favorites']))->assertOk()->assertSee('No lists found.');
    }
}
