<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\ButtonAlias;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboAliasDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_offers_a_remove_aliases_toggle_when_the_combo_uses_an_alias(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        $button = Button::create(['name' => '5LP', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        ButtonAlias::create(['alias' => 'Throw', 'button_idbutton' => $button->idbutton, 'game_idgame' => $game->idgame]);

        $combo = Combo::create([
            'combo' => 'Throw > 5LP',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
        ]);

        $this->get(route('combos.show', $combo))
            ->assertOk()
            ->assertSee('Remove Aliases')
            ->assertSee('id="combo_variant_rendered_dealiased"', false)
            ->assertSee('5LP &gt; 5LP', false);
    }

    public function test_show_page_hides_the_toggle_when_the_combo_uses_no_aliases(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $combo = Combo::create([
            'combo' => '5LP > 5LP',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
        ]);

        $this->get(route('combos.show', $combo))
            ->assertOk()
            ->assertDontSee('Remove Aliases');
    }
}
