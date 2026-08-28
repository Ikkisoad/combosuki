<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\Resource;
use App\Models\ResourceValue;
use App\Models\User;
use App\Services\ComboSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Game, 1: Character, 2: GameEntry} */
    private function makeGameAndCharacter(): array
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        return [$game, $character, $listingType];
    }

    public function test_create_builds_a_combo_from_the_given_attributes(): void
    {
        [$game, $character, $listingType] = $this->makeGameAndCharacter();
        $user = User::create(['nickname' => 'submitter', 'password' => 'password123']);

        $combo = (new ComboSubmissionService)->create($game, [
            'combo' => '5A > 5B > 236B',
            'comments' => 'Meaty setup',
            'video' => 'https://youtu.be/dQw4w9WgXcQ',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 250,
            'type' => $listingType->entryid,
            'patch' => '1.02',
        ], [], $user->iduser);

        $this->assertSame('5A > 5B > 236B', $combo->combo);
        $this->assertSame('Meaty setup', $combo->comments);
        $this->assertSame($character->idcharacter, $combo->character_idcharacter);
        $this->assertSame($listingType->entryid, $combo->type);
        $this->assertSame('1.02', $combo->patch);
        $this->assertSame($user->iduser, $combo->user_iduser);
        $this->assertNull($combo->verified);
        $this->assertNotNull($combo->submited);
    }

    public function test_sync_resources_creates_a_single_row_for_a_list_resource(): void
    {
        [$game, $character, $listingType] = $this->makeGameAndCharacter();

        $resource = GameResource::create(['game_idgame' => $game->idgame, 'text_name' => 'Where?', 'type' => 1, 'primaryORsecundary' => 1]);
        $value = ResourceValue::create(['value' => 'Corner', 'order' => 0, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => $listingType->entryid]);

        (new ComboSubmissionService)->syncResources($combo, $game, [$resource->idgame_resources => $value->idResources_values]);

        $this->assertSame(1, Resource::count());
        $this->assertSame($value->idResources_values, Resource::first()->Resources_values_idResources_values);
        $this->assertNull(Resource::first()->number_value);
    }

    public function test_sync_resources_creates_a_row_per_value_for_a_number_resource(): void
    {
        [$game, $character, $listingType] = $this->makeGameAndCharacter();

        $resource = GameResource::create(['game_idgame' => $game->idgame, 'text_name' => 'Meter', 'type' => 2, 'primaryORsecundary' => 1]);
        $valueA = ResourceValue::create(['value' => 'Bar 1', 'order' => 0, 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $valueB = ResourceValue::create(['value' => 'Bar 2', 'order' => 1, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => $listingType->entryid]);

        (new ComboSubmissionService)->syncResources($combo, $game, [$resource->idgame_resources => '2']);

        $this->assertSame(2, Resource::count());
        $this->assertTrue(Resource::all()->every(fn (Resource $r) => (float) $r->number_value === 2.0));
        $this->assertEqualsCanonicalizing(
            [$valueA->idResources_values, $valueB->idResources_values],
            Resource::pluck('Resources_values_idResources_values')->all()
        );
    }

    public function test_sync_resources_creates_two_rows_for_a_duplicated_resource(): void
    {
        [$game, $character, $listingType] = $this->makeGameAndCharacter();

        $resource = GameResource::create(['game_idgame' => $game->idgame, 'text_name' => 'Assist', 'type' => 3, 'primaryORsecundary' => 1]);
        $first = ResourceValue::create(['value' => 'Assist A', 'order' => 0, 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $second = ResourceValue::create(['value' => 'Assist B', 'order' => 1, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => $listingType->entryid]);

        (new ComboSubmissionService)->syncResources($combo, $game, [
            $resource->idgame_resources => [$first->idResources_values, $second->idResources_values],
        ]);

        $this->assertSame(2, Resource::count());
        $this->assertEqualsCanonicalizing(
            [$first->idResources_values, $second->idResources_values],
            Resource::pluck('Resources_values_idResources_values')->all()
        );
    }

    public function test_sync_resources_skips_a_resource_belonging_to_a_different_game(): void
    {
        [$game, $character, $listingType] = $this->makeGameAndCharacter();
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);

        $foreignResource = GameResource::create(['game_idgame' => $otherGame->idgame, 'text_name' => 'Where?', 'type' => 1, 'primaryORsecundary' => 1]);
        $value = ResourceValue::create(['value' => 'Corner', 'order' => 0, 'game_resources_idgame_resources' => $foreignResource->idgame_resources]);

        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => $listingType->entryid]);

        (new ComboSubmissionService)->syncResources($combo, $game, [$foreignResource->idgame_resources => $value->idResources_values]);

        $this->assertSame(0, Resource::count());
    }

    public function test_sync_resources_replaces_existing_rows(): void
    {
        [$game, $character, $listingType] = $this->makeGameAndCharacter();

        $resource = GameResource::create(['game_idgame' => $game->idgame, 'text_name' => 'Where?', 'type' => 1, 'primaryORsecundary' => 1]);
        $old = ResourceValue::create(['value' => 'Midscreen', 'order' => 0, 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $new = ResourceValue::create(['value' => 'Corner', 'order' => 1, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => $listingType->entryid]);
        $combo->resources()->create(['Resources_values_idResources_values' => $old->idResources_values]);

        (new ComboSubmissionService)->syncResources($combo, $game, [$resource->idgame_resources => $new->idResources_values]);

        $this->assertSame(1, Resource::count());
        $this->assertSame($new->idResources_values, Resource::first()->Resources_values_idResources_values);
    }
}
