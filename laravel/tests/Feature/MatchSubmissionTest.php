<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameResource;
use App\Models\MatchResource;
use App\Models\ResourceValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeGameWithResource(bool $matchesEnabled = true): array
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => $matchesEnabled]);
        $characterOne = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $characterTwo = Character::create(['name' => 'Ken', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Round',
            'type' => 1,
            'primaryORsecundary' => 1,
            'include_in_matches' => true,
        ]);
        $valueOne = ResourceValue::create(['value' => 'Round 1', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $valueTwo = ResourceValue::create(['value' => 'Round 2', 'game_resources_idgame_resources' => $resource->idgame_resources]);

        return [$game, $characterOne, $characterTwo, $resource, $valueOne, $valueTwo];
    }

    public function test_match_creation_form_saves_a_match_with_player_resources(): void
    {
        [$game, $characterOne, $characterTwo, $resource, $valueOne, $valueTwo] = $this->makeGameWithResource();

        $this->actingAs(User::create(['nickname' => 'submitter', 'password' => 'password123']));

        $response = $this->post(route('games.matches.store', $game), [
            'player_one' => 'Alice',
            'player_one_character_idcharacter' => $characterOne->idcharacter,
            'player_two' => 'Bob',
            'player_two_character_idcharacter' => $characterTwo->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now()->toDateString(),
            'player_one_resources' => [$resource->idgame_resources => $valueOne->idResources_values],
            'player_two_resources' => [$resource->idgame_resources => $valueTwo->idResources_values],
        ]);

        $response->assertRedirect(route('games.matches.index', $game));

        $match = GameMatch::firstOrFail();
        $this->assertSame('Alice', $match->player_one);
        $this->assertSame('Bob', $match->player_two);

        $this->assertDatabaseHas('match_resources', [
            'match_idmatch' => $match->idmatch,
            'game_resources_idgame_resources' => $resource->idgame_resources,
            'resources_values_idResources_values' => $valueOne->idResources_values,
            'player' => 1,
        ]);
        $this->assertDatabaseHas('match_resources', [
            'match_idmatch' => $match->idmatch,
            'game_resources_idgame_resources' => $resource->idgame_resources,
            'resources_values_idResources_values' => $valueTwo->idResources_values,
            'player' => 2,
        ]);
    }

    public function test_match_submission_requires_a_value_for_each_primary_match_resource(): void
    {
        [$game, $characterOne, $characterTwo, $resource] = $this->makeGameWithResource();

        $this->actingAs(User::create(['nickname' => 'submitter', 'password' => 'password123']));

        $response = $this->post(route('games.matches.store', $game), [
            'player_one' => 'Alice',
            'player_one_character_idcharacter' => $characterOne->idcharacter,
            'player_two' => 'Bob',
            'player_two_character_idcharacter' => $characterTwo->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now()->toDateString(),
            'player_one_resources' => [$resource->idgame_resources => ''],
        ]);

        $response->assertSessionHasErrors([
            'player_one_resources.'.$resource->idgame_resources,
            'player_two_resources.'.$resource->idgame_resources,
        ]);
        $this->assertDatabaseMissing('matches', ['player_one' => 'Alice']);
    }

    public function test_match_edit_form_preselects_and_update_persists_changed_players_characters_and_resources(): void
    {
        [$game, $characterOne, $characterTwo, $resource, $valueOne, $valueTwo] = $this->makeGameWithResource();

        $this->actingAs(User::create(['nickname' => 'submitter', 'password' => 'password123']));

        $match = GameMatch::create([
            'game_idgame' => $game->idgame,
            'player_one' => 'Alice',
            'player_one_character_idcharacter' => $characterOne->idcharacter,
            'player_two' => 'Bob',
            'player_two_character_idcharacter' => $characterTwo->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now(),
            'user_iduser' => auth()->id(),
        ]);
        MatchResource::create([
            'match_idmatch' => $match->idmatch,
            'game_resources_idgame_resources' => $resource->idgame_resources,
            'resources_values_idResources_values' => $valueOne->idResources_values,
            'player' => 1,
        ]);

        $this->get(route('matches.edit', $match))->assertOk()->assertSee('Alice');

        $response = $this->post(route('matches.update', $match), [
            'player_one' => 'Alice',
            'player_one_character_idcharacter' => $characterTwo->idcharacter,
            'player_two' => 'Charlie',
            'player_two_character_idcharacter' => $characterOne->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now()->toDateString(),
            'player_one_resources' => [$resource->idgame_resources => $valueTwo->idResources_values],
        ]);

        $response->assertRedirect(route('games.matches.index', $game));

        $match->refresh();
        $this->assertSame($characterTwo->idcharacter, $match->player_one_character_idcharacter);
        $this->assertSame('Charlie', $match->player_two);

        $this->assertDatabaseHas('match_resources', [
            'match_idmatch' => $match->idmatch,
            'player' => 1,
            'resources_values_idResources_values' => $valueTwo->idResources_values,
        ]);
    }

    public function test_clearing_a_resource_value_on_update_deletes_its_match_resource_row(): void
    {
        [$game, $characterOne, $characterTwo, $resource, $valueOne] = $this->makeGameWithResource();

        $this->actingAs(User::create(['nickname' => 'submitter', 'password' => 'password123']));

        $match = GameMatch::create([
            'game_idgame' => $game->idgame,
            'player_one' => 'Alice',
            'player_one_character_idcharacter' => $characterOne->idcharacter,
            'player_two' => 'Bob',
            'player_two_character_idcharacter' => $characterTwo->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now(),
            'user_iduser' => auth()->id(),
        ]);
        MatchResource::create([
            'match_idmatch' => $match->idmatch,
            'game_resources_idgame_resources' => $resource->idgame_resources,
            'resources_values_idResources_values' => $valueOne->idResources_values,
            'player' => 1,
        ]);

        $this->post(route('matches.update', $match), [
            'player_one' => 'Alice',
            'player_one_character_idcharacter' => $characterOne->idcharacter,
            'player_two' => 'Bob',
            'player_two_character_idcharacter' => $characterTwo->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now()->toDateString(),
            'player_one_resources' => [$resource->idgame_resources => ''],
        ])->assertRedirect(route('games.matches.index', $game));

        $this->assertDatabaseMissing('match_resources', [
            'match_idmatch' => $match->idmatch,
            'player' => 1,
        ]);
    }

    public function test_disabled_matches_feature_returns_404_for_index_create_and_store(): void
    {
        [$game, $characterOne, $characterTwo] = $this->makeGameWithResource(matchesEnabled: false);

        $this->actingAs(User::create(['nickname' => 'submitter', 'password' => 'password123']));

        $this->get(route('games.matches.index', $game))->assertNotFound();
        $this->get(route('games.matches.create', $game))->assertNotFound();

        $this->post(route('games.matches.store', $game), [
            'player_one' => 'Alice',
            'player_one_character_idcharacter' => $characterOne->idcharacter,
            'player_two' => 'Bob',
            'player_two_character_idcharacter' => $characterTwo->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now()->toDateString(),
        ])->assertNotFound();
    }
}
