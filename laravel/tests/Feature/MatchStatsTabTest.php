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

class MatchStatsTabTest extends TestCase
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

    public function test_stats_tab_counts_character_picks_across_both_player_slots(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => true]);
        $ryu = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $ken = Character::create(['name' => 'Ken', 'game_idgame' => $game->idgame]);
        $chun = Character::create(['name' => 'Chun-Li', 'game_idgame' => $game->idgame]);

        $this->makeMatch($game, $ryu, $ken);
        $this->makeMatch($game, $ken, $ryu);
        $this->makeMatch($game, $ryu, $chun);

        $response = $this->get(route('games.matches.index', $game));

        $response->assertOk();
        $response->assertViewHas('characterPickCounts', function ($counts) use ($ryu, $ken, $chun) {
            $byId = $counts->keyBy(fn (array $entry) => $entry['character']->idcharacter);

            return $byId[$ryu->idcharacter]['picks'] === 3
                && $byId[$ken->idcharacter]['picks'] === 2
                && $byId[$chun->idcharacter]['picks'] === 1;
        });
    }

    public function test_stats_tab_counts_matchups_regardless_of_player_order(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => true]);
        $ryu = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $ken = Character::create(['name' => 'Ken', 'game_idgame' => $game->idgame]);

        $this->makeMatch($game, $ryu, $ken);
        $this->makeMatch($game, $ken, $ryu);

        $response = $this->get(route('games.matches.index', $game));

        $response->assertOk();
        $response->assertViewHas('topMatchups', function ($matchups) use ($ryu, $ken) {
            $matchup = $matchups->first();

            $ids = [$matchup['characterA']->idcharacter, $matchup['characterB']->idcharacter];
            sort($ids);
            $expected = [$ryu->idcharacter, $ken->idcharacter];
            sort($expected);

            return $matchup['count'] === 2 && $ids === $expected;
        });
    }

    public function test_stats_tab_reports_most_used_resource_value_per_character(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => true]);
        $ryu = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $ken = Character::create(['name' => 'Ken', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Stance',
            'type' => 1,
            'primaryORsecundary' => 1,
            'include_in_matches' => true,
        ]);
        $offensive = ResourceValue::create(['value' => 'Offensive', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $defensive = ResourceValue::create(['value' => 'Defensive', 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $matchOne = $this->makeMatch($game, $ryu, $ken);
        MatchResource::create(['match_idmatch' => $matchOne->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $offensive->idResources_values, 'player' => 1]);

        $matchTwo = $this->makeMatch($game, $ryu, $ken);
        MatchResource::create(['match_idmatch' => $matchTwo->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $offensive->idResources_values, 'player' => 1]);

        $matchThree = $this->makeMatch($game, $ryu, $ken);
        MatchResource::create(['match_idmatch' => $matchThree->idmatch, 'game_resources_idgame_resources' => $resource->idgame_resources, 'resources_values_idResources_values' => $defensive->idResources_values, 'player' => 1]);

        $response = $this->get(route('games.matches.index', $game));

        $response->assertOk();
        $response->assertViewHas('characterResourceUsage', function ($usage) use ($ryu) {
            $entry = $usage->firstWhere(fn (array $entry) => $entry['character']->idcharacter === $ryu->idcharacter);

            return $entry !== null
                && $entry['value']->idResources_values !== null
                && $entry['value']->value === 'Offensive'
                && $entry['uses'] === 2;
        });
    }

    public function test_stats_tab_resource_usage_is_empty_when_game_has_no_match_resources(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'matches_enabled' => true]);
        $ryu = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $ken = Character::create(['name' => 'Ken', 'game_idgame' => $game->idgame]);

        $this->makeMatch($game, $ryu, $ken);

        $response = $this->get(route('games.matches.index', $game));

        $response->assertOk();
        $response->assertViewHas('characterResourceUsage', fn ($usage) => $usage->isEmpty());
    }
}
