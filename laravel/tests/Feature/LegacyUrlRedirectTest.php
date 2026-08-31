<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyUrlRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Legacy Fighter', 'image' => 'legacy.png', 'modPass' => 'secret']);
    }

    public function test_game_index_php_redirects_to_game_page(): void
    {
        $this->get("/game/index.php?gameid={$this->game->idgame}")
            ->assertRedirect(route('games.show', $this->game));
    }

    public function test_game_index_php_without_gameid_redirects_to_games_index(): void
    {
        $this->get('/game/index.php')->assertRedirect(route('games.index'));
    }

    public function test_combo_php_redirects_to_combo_page(): void
    {
        $character = Character::create(['name' => 'Legacy Character', 'game_idgame' => $this->game->idgame]);

        $combo = Combo::create([
            'character_idcharacter' => $character->idcharacter,
            'combo' => '5A > 5B',
            'type' => 1,
        ]);

        $this->get("/game/combo.php?idcombo={$combo->idcombo}")
            ->assertRedirect(route('combos.show', $combo));
    }

    public function test_submit_php_redirects_to_game_combos_index(): void
    {
        $this->get("/game/submit.php?gameid={$this->game->idgame}&combo=foo")
            ->assertRedirect(route('games.combos.index', $this->game));
    }

    public function test_game_edit_pages_redirect_to_their_admin_equivalents(): void
    {
        $cases = [
            'game/edit/game.php' => 'admin.game.edit',
            'game/edit/characters.php' => 'admin.characters.index',
            'game/edit/buttons.php' => 'admin.buttons.index',
            'game/edit/entries.php' => 'admin.entries.index',
            'game/edit/links.php' => 'admin.links.index',
            'game/edit/lists.php' => 'admin.lists.index',
            'game/edit/resources.php' => 'admin.resources.index',
            'game/edit/mass.php' => 'admin.game.edit',
        ];

        foreach ($cases as $legacyPath => $routeName) {
            $this->get("/{$legacyPath}?gameid={$this->game->idgame}")
                ->assertRedirect(route($routeName, $this->game));
        }
    }

    public function test_list_show_php_redirects_to_list_page(): void
    {
        $list = ListModel::create(['list_name' => 'My List', 'game_idgame' => $this->game->idgame, 'password' => 'unused', 'type' => 1]);

        $this->get("/list/show.php?id={$list->idlist}")
            ->assertRedirect(route('lists.show', $list));

        $this->get("/list/list.php?listid={$list->idlist}")
            ->assertRedirect(route('lists.show', $list));
    }

    public function test_list_index_and_search_php_redirect(): void
    {
        $this->get('/list/index.php')->assertRedirect(route('lists.index'));

        $this->get("/list/search.php?gameid={$this->game->idgame}&q=combo")
            ->assertRedirect(route('lists.search', ['gameid' => $this->game->idgame, 'q' => 'combo']));
    }
}
