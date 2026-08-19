<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_combo_creation_form_offers_a_listing_type_and_saves_it(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $okizeme = GameEntry::where('gameid', $game->idgame)->where('title', 'Okizeme')->firstOrFail();

        $this->get(route('games.combos.create', $game))
            ->assertOk()
            ->assertSee('Type:')
            ->assertSee('Okizeme');

        $response = $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $okizeme->entryid,
            'combo' => '5A > 5B',
        ]);

        $combo = Combo::firstOrFail();
        $response->assertRedirect(route('combos.show', $combo));
        $this->assertSame($okizeme->entryid, $combo->type);
    }

    public function test_combo_edit_form_preselects_and_updates_the_listing_type(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $comboType = GameEntry::where('gameid', $game->idgame)->where('title', 'Combo')->firstOrFail();
        $mixUp = GameEntry::where('gameid', $game->idgame)->where('title', 'Mix Up')->firstOrFail();

        $combo = Combo::create([
            'combo' => '5A > 5B',
            'character_idcharacter' => $character->idcharacter,
            'submited' => now(),
            'type' => $comboType->entryid,
        ]);

        $this->get(route('combos.edit', $combo))->assertOk()->assertSee('Type:');

        $response = $this->post(route('combos.update', $combo), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $mixUp->entryid,
            'combo' => '5A > 5B > 5C',
        ]);

        $response->assertRedirect(route('combos.show', $combo));
        $this->assertSame($mixUp->entryid, $combo->fresh()->type);
    }
}
