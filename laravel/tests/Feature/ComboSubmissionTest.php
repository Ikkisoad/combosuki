<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ResourceValue;
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

    public function test_arriving_from_a_challenge_prefills_the_forms_query_criteria(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $comboType = GameEntry::where('gameid', $game->idgame)->where('title', 'Combo')->firstOrFail();
        $whereResource = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Where?')->firstOrFail();
        $corner = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Corner')->firstOrFail();

        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter, corner, no meter',
            'filters' => [
                'combo' => '2LK',
                'combolike' => 0,
                'damage' => '1000',
                'patch' => '1.0',
                'comments' => 'no meter#corner only',
                'listingtype' => $comboType->entryid,
                'Where?' => $corner->idResources_values,
            ],
            'order' => 0,
        ]);

        $response = $this->get(route('games.combos.create', $game).'?query='.$query->idquery.'&characterid='.$character->idcharacter);

        $response->assertOk()
            ->assertSee('>2LK</textarea>', false)
            ->assertSee('value="1000"', false)
            ->assertSee('value="1.0"', false)
            ->assertSee('no meter, corner only')
            ->assertSee('value="'.$character->idcharacter.'" selected', false)
            ->assertSee('value="'.$comboType->entryid.'" selected', false)
            ->assertSee('value="'.$corner->idResources_values.'" selected', false);
    }
}
