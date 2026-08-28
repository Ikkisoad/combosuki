<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\ButtonAlias;
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
}
