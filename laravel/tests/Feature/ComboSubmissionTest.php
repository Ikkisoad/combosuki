<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\ButtonAlias;
use App\Models\Character;
use App\Models\CharacterButtonAlias;
use App\Models\CharacterQuery;
use App\Models\CharacterResourceValueAlias;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GamePatch;
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
        $whereResource = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Where?')->firstOrFail();
        $midscreen = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Midscreen')->firstOrFail();

        $this->get(route('games.combos.create', $game))
            ->assertOk()
            ->assertSee('Type:')
            ->assertSee('Okizeme');

        $response = $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $okizeme->entryid,
            'combo' => '5A > 5B',
            'resources' => [$whereResource->idgame_resources => $midscreen->idResources_values],
        ]);

        $combo = Combo::firstOrFail();
        $response->assertRedirect(route('combos.show', $combo));
        $this->assertSame($okizeme->entryid, $combo->type);
    }

    public function test_combo_creation_form_offers_button_aliases_behind_a_reveal_link(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $button = Button::where('game_idgame', $game->idgame)->firstOrFail();
        ButtonAlias::create(['alias' => 'Throw', 'button_idbutton' => $button->idbutton, 'game_idgame' => $game->idgame]);

        $this->get(route('games.combos.create', $game))
            ->assertOk()
            ->assertSee('Other button names&hellip;', false)
            ->assertSee('onclick="moveNumbers(\''.$button->name.'\')"', false)
            ->assertSee('>Throw<', false);
    }

    public function test_combo_creation_form_hides_the_alias_reveal_link_when_no_aliases_exist(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();

        $this->get(route('games.combos.create', $game))
            ->assertOk()
            ->assertDontSee('Other button names&hellip;', false);
    }

    public function test_combo_creation_form_ships_character_specific_button_aliases_for_js_to_filter_by_character(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $button = Button::where('game_idgame', $game->idgame)->firstOrFail();
        CharacterButtonAlias::create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton, 'character_idcharacter' => $character->idcharacter]);

        $this->get(route('games.combos.create', $game))
            ->assertOk()
            ->assertSee('Other button names&hellip;', false)
            ->assertSee('id="character-button-aliases-data"', false)
            ->assertSee('{"'.$character->idcharacter.'":[{"alias":"Tackle","buttonName":"'.$button->name.'"', false);
    }

    public function test_combo_creation_form_offers_the_alias_reveal_link_when_only_a_character_alias_exists(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $button = Button::where('game_idgame', $game->idgame)->firstOrFail();
        CharacterButtonAlias::create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton, 'character_idcharacter' => $character->idcharacter]);

        // No game-wide ButtonAlias exists here — only a character-scoped
        // one — so the reveal link must be driven by both sources, not just
        // $game->buttonAliases().
        $this->get(route('games.combos.create', $game))
            ->assertOk()
            ->assertSee('Other button names&hellip;', false);
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
        $patch = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => now()->subDay()]);

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
            ->assertSee('value="'.$patch->idgame_patch.'" selected', false)
            ->assertSee('no meter, corner only')
            ->assertSee('value="'.$character->idcharacter.'" selected', false)
            ->assertSee('value="'.$comboType->entryid.'" selected', false)
            ->assertSee('value="'.$corner->idResources_values.'" selected', false);
    }

    public function test_arriving_from_the_randomizer_prefills_the_rolled_character_and_resources(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $whereResource = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Where?')->firstOrFail();
        $corner = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Corner')->firstOrFail();

        $resources = json_encode([$whereResource->idgame_resources => $corner->idResources_values]);

        $response = $this->get(route('games.combos.create', $game).'?character_idcharacter='.$character->idcharacter
            .'&resources='.urlencode($resources));

        $response->assertOk()
            ->assertSee('value="'.$character->idcharacter.'" selected', false)
            ->assertSee('value="'.$corner->idResources_values.'" selected', false);
    }

    public function test_the_game_pages_randomizer_tab_ships_characters_and_primary_resources_for_the_client_side_roll(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $whereResource = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Where?')->firstOrFail();

        $this->get(route('games.show', $game))
            ->assertOk()
            ->assertSee('id="randomizer-tab"', false)
            ->assertSee('id="randomizer-data"', false)
            ->assertSee('"name":"'.$character->name.'"', false)
            ->assertSee('"name":"Where?"', false)
            ->assertSee('"id":'.$whereResource->idgame_resources, false);
    }

    public function test_secondary_resources_render_with_their_linked_characters_for_the_combo_forms_toggle(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();

        $linkedResource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Assist',
            'type' => 1,
            'primaryORsecundary' => 0,
        ]);
        ResourceValue::create(['value' => 'Type A', 'game_resources_idgame_resources' => $linkedResource->idgame_resources]);
        $linkedResource->characters()->attach($character->idcharacter);

        $unrestrictedResource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Position',
            'type' => 1,
            'primaryORsecundary' => 0,
        ]);
        ResourceValue::create(['value' => 'Corner', 'game_resources_idgame_resources' => $unrestrictedResource->idgame_resources]);

        $response = $this->get(route('games.combos.create', $game));

        $response->assertOk()
            ->assertSee('data-characters="'.$character->idcharacter.'"', false)
            ->assertSee('data-characters=""', false)
            ->assertSee('Show all secondary resources');
    }

    /**
     * Type-3 ("Duplicated") resources let a combo pick the same resource
     * twice, e.g. Skullgirls' Assist field (two assists per combo). The
     * create/edit forms used to omit type-3 resources entirely, so this
     * guards both that the two selects render and that both submitted
     * values are persisted and preselected on edit.
     */
    public function test_duplicated_resource_offers_two_selects_and_round_trips_both_values(): void
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
        $midscreen = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Midscreen')->firstOrFail();

        $assist = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Assist',
            'type' => 3,
            'primaryORsecundary' => 1,
        ]);
        $assistA = ResourceValue::create(['value' => 'Painwheel', 'game_resources_idgame_resources' => $assist->idgame_resources]);
        $assistB = ResourceValue::create(['value' => 'Valentine', 'game_resources_idgame_resources' => $assist->idgame_resources]);

        $this->get(route('games.combos.create', $game))
            ->assertOk()
            ->assertSee('name="resources['.$assist->idgame_resources.'][]"', false);

        $response = $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $comboType->entryid,
            'combo' => '5A > 5B',
            'resources' => [
                $whereResource->idgame_resources => $midscreen->idResources_values,
                $assist->idgame_resources => [$assistA->idResources_values, $assistB->idResources_values],
            ],
        ]);

        $combo = Combo::firstOrFail();
        $response->assertRedirect(route('combos.show', $combo));

        $this->assertSame(
            [$assistA->idResources_values, $assistB->idResources_values],
            $combo->resources()
                ->whereIn('Resources_values_idResources_values', [$assistA->idResources_values, $assistB->idResources_values])
                ->pluck('Resources_values_idResources_values')->sort()->values()->all()
        );

        $this->get(route('combos.edit', $combo))
            ->assertOk()
            ->assertSee('value="'.$assistA->idResources_values.'" selected', false)
            ->assertSee('value="'.$assistB->idResources_values.'" selected', false);
    }

    public function test_combo_creation_form_has_no_blank_option_and_preselects_the_first_ordered_value_for_a_primary_resource(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $whereResource = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Where?')->firstOrFail();
        $midscreen = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Midscreen')->firstOrFail();

        $response = $this->get(route('games.combos.create', $game));

        $response->assertOk()
            ->assertDontSee('value="-"', false)
            ->assertSeeInOrder([
                'name="resources['.$whereResource->idgame_resources.']"',
                'value="'.$midscreen->idResources_values.'"',
            ], false);
    }

    public function test_combo_submission_requires_a_value_for_a_primary_list_resource(): void
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

        $response = $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $comboType->entryid,
            'combo' => '5A > 5B',
        ]);

        $response->assertSessionHasErrors(['resources.'.$whereResource->idgame_resources]);
        $this->assertDatabaseMissing('combo', ['combo' => '5A > 5B']);
    }

    public function test_combo_submission_requires_both_slots_of_a_primary_duplicated_resource(): void
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
        $midscreen = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Midscreen')->firstOrFail();

        $assist = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Assist',
            'type' => 3,
            'primaryORsecundary' => 1,
        ]);
        $assistB = ResourceValue::create(['value' => 'Valentine', 'game_resources_idgame_resources' => $assist->idgame_resources]);

        $missingFirstSlot = $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $comboType->entryid,
            'combo' => '5A > 5B',
            'resources' => [
                $whereResource->idgame_resources => $midscreen->idResources_values,
                $assist->idgame_resources => ['', $assistB->idResources_values],
            ],
        ]);

        $missingFirstSlot->assertSessionHasErrors(['resources.'.$assist->idgame_resources.'.0']);

        $missingSecondSlot = $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $comboType->entryid,
            'combo' => '5A > 5B',
            'resources' => [
                $whereResource->idgame_resources => $midscreen->idResources_values,
                $assist->idgame_resources => [$assistB->idResources_values, ''],
            ],
        ]);

        $missingSecondSlot->assertSessionHasErrors(['resources.'.$assist->idgame_resources.'.1']);
        $this->assertDatabaseMissing('combo', ['combo' => '5A > 5B']);
    }

    public function test_duplicated_resource_selects_have_no_blank_option_and_both_default_to_the_same_first_value(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();

        $assist = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Assist',
            'type' => 3,
            'primaryORsecundary' => 1,
        ]);
        $painwheel = ResourceValue::create(['value' => 'Painwheel', 'order' => 0, 'game_resources_idgame_resources' => $assist->idgame_resources]);
        ResourceValue::create(['value' => 'Valentine', 'order' => 1, 'game_resources_idgame_resources' => $assist->idgame_resources]);

        $response = $this->get(route('games.combos.create', $game));

        $response->assertOk()->assertDontSee('value="-"', false);

        // No option carries an explicit `selected` attribute here (nothing in
        // `old()`/`$defaults` picks one), so the "default" is the browser's
        // native behaviour of treating a select's first <option> as chosen.
        // With no blank option ahead of it, that first <option> in both
        // Assist slots must be Painwheel (order 0).
        $html = $response->getContent();
        $this->assertSame(
            2,
            preg_match_all(
                '/<select name="resources\['.$assist->idgame_resources.'\]\[\]"[^>]*>\s*<option value="'.$painwheel->idResources_values.'"/',
                $html
            ),
            'Expected both Assist slots\' first option to be Painwheel (the first value by order).'
        );
    }

    public function test_combo_creation_form_defaults_a_primary_number_resource_to_zero(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();

        $meter = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Meter',
            'type' => 2,
            'primaryORsecundary' => 1,
        ]);
        ResourceValue::create(['value' => 100, 'game_resources_idgame_resources' => $meter->idgame_resources]);

        $response = $this->get(route('games.combos.create', $game));

        $response->assertOk()->assertSeeInOrder([
            'name="resources['.$meter->idgame_resources.']"',
            'required',
            'value="0"',
        ], false);
    }

    public function test_combo_submission_requires_a_value_for_a_primary_number_resource_but_accepts_zero(): void
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
        $midscreen = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Midscreen')->firstOrFail();

        $meter = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Meter',
            'type' => 2,
            'primaryORsecundary' => 1,
        ]);
        ResourceValue::create(['value' => 100, 'game_resources_idgame_resources' => $meter->idgame_resources]);

        $missing = $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $comboType->entryid,
            'combo' => '5A > 5B',
            'resources' => [$whereResource->idgame_resources => $midscreen->idResources_values],
        ]);

        $missing->assertSessionHasErrors(['resources.'.$meter->idgame_resources]);
        $this->assertDatabaseMissing('combo', ['combo' => '5A > 5B']);

        $withZero = $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $comboType->entryid,
            'combo' => '5A > 5B',
            'resources' => [
                $whereResource->idgame_resources => $midscreen->idResources_values,
                $meter->idgame_resources => 0,
            ],
        ]);

        $combo = Combo::firstOrFail();
        $withZero->assertRedirect(route('combos.show', $combo));
        $this->assertDatabaseHas('resources', [
            'combo_idcombo' => $combo->idcombo,
            'number_value' => 0,
        ]);
    }

    public function test_the_inline_quick_edit_form_can_still_save_without_touching_resources(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $comboType = GameEntry::where('gameid', $game->idgame)->where('title', 'Combo')->firstOrFail();

        $combo = Combo::create([
            'combo' => '5A > 5B',
            'character_idcharacter' => $character->idcharacter,
            'submited' => now(),
            'type' => $comboType->entryid,
        ]);

        // The quick-edit form on combos/show.blade.php never sends a
        // `resources` key at all, so it must stay unaffected by the
        // required-primary-resources rule that only kicks in when the
        // advanced edit form actually submits one.
        $response = $this->post(route('combos.update', $combo), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $comboType->entryid,
            'combo' => '5A > 5B > 5C',
        ]);

        $response->assertRedirect(route('combos.show', $combo));
        $this->assertSame('5A > 5B > 5C', $combo->fresh()->combo);
    }

    /**
     * The character select on the create form has no server round-trip
     * (see filterSecondaryResources()/updateResourceValueAliases() in
     * app.js), so per-character resource value aliases can't be rendered
     * into the <option> text server-side. Instead the form ships every
     * character's overrides as a {characterId: {resourceValueId: alias}}
     * JSON blob, plus a data-default-label on each <option> to fall back to.
     */
    public function test_combo_creation_form_embeds_resource_value_aliases_for_client_side_swapping(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();

        $support = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Support',
            'type' => 1,
            'primaryORsecundary' => 1,
        ]);
        $one = ResourceValue::create(['value' => '1', 'game_resources_idgame_resources' => $support->idgame_resources]);

        CharacterResourceValueAlias::create([
            'alias' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'resources_values_idResources_values' => $one->idResources_values,
        ]);

        $response = $this->get(route('games.combos.create', $game));

        $response->assertOk()
            ->assertSee('id="resource-value-aliases"', false)
            ->assertSee('"'.$one->idResources_values.'":"A"', false)
            ->assertSee('class="form-select resource-value-select"', false)
            ->assertSee('data-default-label="1"', false);
    }

    public function test_combo_show_page_displays_the_characters_resource_value_alias(): void
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
        $midscreen = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Midscreen')->firstOrFail();

        CharacterResourceValueAlias::create([
            'alias' => 'Screen Center',
            'character_idcharacter' => $character->idcharacter,
            'resources_values_idResources_values' => $midscreen->idResources_values,
        ]);

        $combo = Combo::create([
            'combo' => '5A > 5B',
            'character_idcharacter' => $character->idcharacter,
            'submited' => now(),
            'type' => $comboType->entryid,
        ]);
        $combo->resources()->create(['Resources_values_idResources_values' => $midscreen->idResources_values]);

        $this->get(route('combos.show', $combo))
            ->assertOk()
            ->assertSee('Screen Center')
            ->assertDontSee('>Midscreen<', false);
    }

    public function test_a_submitted_combo_appears_on_the_games_show_latest_combos_tab(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $okizeme = GameEntry::where('gameid', $game->idgame)->where('title', 'Okizeme')->firstOrFail();
        $whereResource = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Where?')->firstOrFail();
        $midscreen = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Midscreen')->firstOrFail();

        $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $okizeme->entryid,
            'combo' => '2LK',
            'resources' => [$whereResource->idgame_resources => $midscreen->idResources_values],
        ]);

        $combo = Combo::firstOrFail();

        // Viewed in the same authenticated session as the submitter:
        // Combo::scopeVisibleTo() only lets a fresh, unverified combo through
        // to a non-trusted viewer via the "viewer is the author" branch, so
        // a different viewer wouldn't see it here without going through
        // verification (covered separately by ComboVerificationTest).
        $this->get(route('games.show', $game))
            ->assertOk()
            ->assertSee('Latest Combos')
            ->assertSee($combo->combo)
            ->assertSee($character->name);
    }

    public function test_a_submitted_combo_is_findable_via_the_combo_search_index(): void
    {
        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        $game = Game::where('name', 'New Fighter')->firstOrFail();
        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $okizeme = GameEntry::where('gameid', $game->idgame)->where('title', 'Okizeme')->firstOrFail();
        $whereResource = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Where?')->firstOrFail();
        $midscreen = ResourceValue::where('game_resources_idgame_resources', $whereResource->idgame_resources)->where('value', 'Midscreen')->firstOrFail();

        $this->post(route('games.combos.store', $game), [
            'character_idcharacter' => $character->idcharacter,
            'listingtype' => $okizeme->entryid,
            'combo' => '2LK',
            'resources' => [$whereResource->idgame_resources => $midscreen->idResources_values],
        ]);

        $combo = Combo::firstOrFail();

        $this->get(route('games.combos.index', $game).'?combo=2LK')
            ->assertOk()
            ->assertSee('1 result(s)')
            ->assertSee($combo->combo);
    }
}
