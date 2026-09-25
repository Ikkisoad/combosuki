<?php

namespace Tests\Feature;

use App\Models\CharacterQuery;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamesIndexNoDefaultQueriesHighlightTest extends TestCase
{
    use RefreshDatabase;

    private const HIGHLIGHT = 'No default queries';

    private Game $gameWithoutQueries;

    private Game $gameWithQueries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameWithoutQueries = Game::create(['name' => 'Game Without Queries', 'complete' => 1, 'modPass' => 'secret']);

        $this->gameWithQueries = Game::create(['name' => 'Game With Queries', 'complete' => 1, 'modPass' => 'secret']);
        CharacterQuery::create([
            'game_idgame' => $this->gameWithQueries->idgame,
            'label' => 'Max damage',
            'filters' => [],
            'order' => 1,
        ]);
    }

    public function test_admin_sees_highlight_only_on_games_without_queries(): void
    {
        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $this->actingAs($admin);

        $content = $this->get(route('games.index'))->assertOk()->getContent();

        // Admin sees both games, but only one of them lacks queries.
        $this->assertSame(1, substr_count($content, self::HIGHLIGHT));
    }

    public function test_moderator_only_sees_highlight_on_their_own_moderated_game(): void
    {
        $moderator = User::create(['nickname' => 'mod', 'password' => 'password123', 'is_moderator' => true]);
        $this->gameWithQueries->moderators()->attach($moderator->iduser);

        $this->actingAs($moderator);

        $this->get(route('games.index'))->assertOk()->assertDontSee(self::HIGHLIGHT);

        $this->gameWithoutQueries->moderators()->attach($moderator->iduser);

        $this->get(route('games.index'))->assertOk()->assertSee(self::HIGHLIGHT);
    }

    public function test_trusted_user_without_assignment_does_not_see_the_highlight(): void
    {
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $this->actingAs($trusted);

        $this->get(route('games.index'))->assertOk()->assertDontSee(self::HIGHLIGHT);
    }

    public function test_guest_and_untrusted_user_never_see_the_highlight(): void
    {
        $this->get(route('games.index'))->assertOk()->assertDontSee(self::HIGHLIGHT);

        $untrusted = User::create(['nickname' => 'plain', 'password' => 'password123']);
        $this->actingAs($untrusted);
        $this->get(route('games.index'))->assertOk()->assertDontSee(self::HIGHLIGHT);
    }
}
