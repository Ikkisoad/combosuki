<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameResource;
use App\Models\MatchResource;
use App\Models\ResourceValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeMatch(Game $game, Character $one, Character $two, string $playerOneName = 'Alice', string $playerTwoName = 'Bob'): GameMatch
    {
        return GameMatch::create([
            'game_idgame' => $game->idgame,
            'player_one' => $playerOneName,
            'player_one_character_idcharacter' => $one->idcharacter,
            'player_two' => $playerTwoName,
            'player_two_character_idcharacter' => $two->idcharacter,
            'video' => 'https://example.com/video',
            'played_at' => now(),
        ]);
    }

    public function test_character_pair_filter_is_order_independent(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => true]);
        $ryu = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $ken = Character::create(['name' => 'Ken', 'game_idgame' => $game->idgame]);
        $chun = Character::create(['name' => 'Chun-Li', 'game_idgame' => $game->idgame]);

        $forward = $this->makeMatch($game, $ryu, $ken, 'ForwardMatch', 'Bob');
        $reversed = $this->makeMatch($game, $ken, $ryu, 'ReversedMatch', 'Bob');
        $unrelated = $this->makeMatch($game, $ryu, $chun, 'UnrelatedMatch', 'Bob');

        $response = $this->get(route('games.matches.index', $game).'?character_a='.$ryu->idcharacter.'&character_b='.$ken->idcharacter);

        $response->assertOk();
        $response->assertSee('ForwardMatch');
        $response->assertSee('ReversedMatch');
        $response->assertDontSee('UnrelatedMatch');
    }

    public function test_single_character_filter_matches_either_player_slot(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => true]);
        $ryu = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $ken = Character::create(['name' => 'Ken', 'game_idgame' => $game->idgame]);
        $chun = Character::create(['name' => 'Chun-Li', 'game_idgame' => $game->idgame]);

        $ryuFirst = $this->makeMatch($game, $ryu, $ken, 'RyuFirst', 'Bob');
        $ryuSecond = $this->makeMatch($game, $chun, $ryu, 'RyuSecond', 'Bob');
        $noRyu = $this->makeMatch($game, $ken, $chun, 'NoRyu', 'Bob');

        $response = $this->get(route('games.matches.index', $game).'?character_a='.$ryu->idcharacter);

        $response->assertOk();
        $response->assertSee('RyuFirst');
        $response->assertSee('RyuSecond');
        $response->assertDontSee('NoRyu');
    }

    public function test_resource_pair_filter_matches_either_player_assignment_order(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => true]);
        $ryu = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $ken = Character::create(['name' => 'Ken', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Round',
            'type' => 1,
            'primaryORsecundary' => 1,
            'include_in_matches' => true,
        ]);
        $win = ResourceValue::create(['value' => 'Win', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $lose = ResourceValue::create(['value' => 'Lose', 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $forward = $this->makeMatch($game, $ryu, $ken, 'ForwardResourceMatch', 'Bob');
        MatchResource::create(['match_idmatch' => $forward->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $win->idResources_values, 'player' => 1]);
        MatchResource::create(['match_idmatch' => $forward->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $lose->idResources_values, 'player' => 2]);

        $reversed = $this->makeMatch($game, $ryu, $ken, 'ReversedResourceMatch', 'Bob');
        MatchResource::create(['match_idmatch' => $reversed->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $lose->idResources_values, 'player' => 1]);
        MatchResource::create(['match_idmatch' => $reversed->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $win->idResources_values, 'player' => 2]);

        $noMatch = $this->makeMatch($game, $ryu, $ken, 'NoResourceMatch', 'Bob');
        MatchResource::create(['match_idmatch' => $noMatch->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $lose->idResources_values, 'player' => 1]);
        MatchResource::create(['match_idmatch' => $noMatch->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $lose->idResources_values, 'player' => 2]);

        $field = 'resource_'.$resource->idgame_resources;
        $response = $this->get(route('games.matches.index', $game).'?'.$field.'_a='.$win->idResources_values.'&'.$field.'_b='.$lose->idResources_values);

        $response->assertOk();
        $response->assertSee('ForwardResourceMatch');
        $response->assertSee('ReversedResourceMatch');
        $response->assertDontSee('NoResourceMatch');
    }

    public function test_single_resource_value_filter_matches_a_match_with_that_value_on_either_player(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => true]);
        $ryu = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $ken = Character::create(['name' => 'Ken', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Round',
            'type' => 1,
            'primaryORsecundary' => 1,
            'include_in_matches' => true,
        ]);
        $win = ResourceValue::create(['value' => 'Win', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $lose = ResourceValue::create(['value' => 'Lose', 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $winnerOne = $this->makeMatch($game, $ryu, $ken, 'WinnerIsPlayerOne', 'Bob');
        MatchResource::create(['match_idmatch' => $winnerOne->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $win->idResources_values, 'player' => 1]);

        $winnerTwo = $this->makeMatch($game, $ryu, $ken, 'WinnerIsPlayerTwo', 'Bob');
        MatchResource::create(['match_idmatch' => $winnerTwo->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $win->idResources_values, 'player' => 2]);

        $loserOnly = $this->makeMatch($game, $ryu, $ken, 'LoserOnlyMatch', 'Bob');
        MatchResource::create(['match_idmatch' => $loserOnly->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $lose->idResources_values, 'player' => 1]);

        $field = 'resource_'.$resource->idgame_resources;
        $response = $this->get(route('games.matches.index', $game).'?'.$field.'_a='.$win->idResources_values);

        $response->assertOk();
        $response->assertSee('WinnerIsPlayerOne');
        $response->assertSee('WinnerIsPlayerTwo');
        $response->assertDontSee('LoserOnlyMatch');
    }
}
