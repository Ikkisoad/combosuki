<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\GameResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameResourceTierListFlagTest extends TestCase
{
    use RefreshDatabase;

    private function createGameAsModerator(): Game
    {
        $this->post(route('games.store'), [
            'name' => 'New Fighter',
            'image' => 'https://example.com/new-fighter.png',
        ]);

        return Game::where('name', 'New Fighter')->firstOrFail();
    }

    public function test_include_in_tier_lists_is_forced_false_on_a_secondary_resource(): void
    {
        $this->actingAs(User::create(['nickname' => 'moderator', 'password' => 'password123', 'trusted_user' => true]));
        $game = $this->createGameAsModerator();

        $this->post(route('admin.resources.store', $game), [
            'action' => 'Add',
            'resource' => 'Assist',
            'type' => 1,
            'primaryORsecundary' => 0,
            'include_in_tier_lists' => '1',
        ]);

        $resource = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Assist')->firstOrFail();

        $this->assertFalse($resource->include_in_tier_lists);
    }

    public function test_flagging_a_new_primary_resource_clears_the_flag_from_another_resource_in_the_same_game(): void
    {
        $this->actingAs(User::create(['nickname' => 'moderator', 'password' => 'password123', 'trusted_user' => true]));
        $game = $this->createGameAsModerator();

        $moonType = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Moon Type',
            'type' => 1,
            'primaryORsecundary' => 1,
            'include_in_tier_lists' => true,
        ]);

        $this->post(route('admin.resources.store', $game), [
            'action' => 'Add',
            'resource' => 'Heat',
            'type' => 1,
            'primaryORsecundary' => 1,
            'include_in_tier_lists' => '1',
        ]);

        $heat = GameResource::where('game_idgame', $game->idgame)->where('text_name', 'Heat')->firstOrFail();

        $this->assertTrue($heat->include_in_tier_lists);
        $this->assertFalse($moonType->fresh()->include_in_tier_lists);
    }

    public function test_save_all_keeps_only_the_last_checked_resource_when_multiple_are_flagged(): void
    {
        $this->actingAs(User::create(['nickname' => 'moderator', 'password' => 'password123', 'trusted_user' => true]));
        $game = $this->createGameAsModerator();

        $moonType = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Moon Type',
            'type' => 1,
            'primaryORsecundary' => 1,
        ]);

        $heat = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Heat',
            'type' => 1,
            'primaryORsecundary' => 1,
        ]);

        $this->post(route('admin.resources.store', $game), [
            'action' => 'SaveAll',
            'resources' => [
                $moonType->idgame_resources => [
                    'resource' => 'Moon Type',
                    'type' => 1,
                    'primaryORsecundary' => 1,
                    'include_in_tier_lists' => '1',
                ],
                $heat->idgame_resources => [
                    'resource' => 'Heat',
                    'type' => 1,
                    'primaryORsecundary' => 1,
                    'include_in_tier_lists' => '1',
                ],
            ],
        ]);

        $this->assertFalse($moonType->fresh()->include_in_tier_lists);
        $this->assertTrue($heat->fresh()->include_in_tier_lists);
    }
}
