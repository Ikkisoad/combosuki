<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\TierList;
use App\Models\TierListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierListImageRouteTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function test_image_route_returns_a_png(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $tierList = TierList::create(['title' => 'My Tier List', 'game_idgame' => $game->idgame]);
        TierListEntry::create([
            'tier_list_idtier_list' => $tierList->idtier_list,
            'character_idcharacter' => $character->idcharacter,
            'tier' => 'S',
            'order' => 0,
        ]);

        $response = $this->get(route('tier-lists.image', $tierList));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith(self::PNG_SIGNATURE, $response->getContent());
    }

    public function test_show_page_links_the_image_route_as_its_og_and_twitter_image(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $tierList = TierList::create(['title' => 'My Tier List', 'game_idgame' => $game->idgame]);

        $response = $this->get(route('tier-lists.show', $tierList));

        $response->assertOk();
        $response->assertSee(route('tier-lists.image', $tierList), false);
    }
}
