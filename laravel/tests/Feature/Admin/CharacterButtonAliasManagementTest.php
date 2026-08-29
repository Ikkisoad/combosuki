<?php

namespace Tests\Feature\Admin;

use App\Models\Button;
use App\Models\Character;
use App\Models\CharacterButtonAlias;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterButtonAliasManagementTest extends TestCase
{
    use RefreshDatabase;

    private function trustedUser(Game $game): User
    {
        $user = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($user->iduser);

        return $user;
    }

    private function tackleButton(Game $game): Button
    {
        return Button::create(['name' => '236A', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame]);
    }

    private function character(Game $game, string $name = 'Toph'): Character
    {
        return Character::create(['name' => $name, 'game_idgame' => $game->idgame]);
    }

    public function test_adding_a_character_button_alias(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->tackleButton($game);
        $character = $this->character($game);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.character-button-aliases.store', $game), [
            'action' => 'Add',
            'alias' => 'Tackle',
            'button_idbutton' => $button->idbutton,
            'character_idcharacter' => $character->idcharacter,
        ])->assertRedirect(route('admin.character-button-aliases.index', $game));

        $this->assertDatabaseHas('character_button_alias', [
            'alias' => 'Tackle',
            'button_idbutton' => $button->idbutton,
            'character_idcharacter' => $character->idcharacter,
        ]);
    }

    public function test_a_character_button_alias_cannot_point_at_a_button_from_another_game(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $foreignButton = $this->tackleButton($otherGame);
        $character = $this->character($game);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.character-button-aliases.store', $game), [
            'action' => 'Add',
            'alias' => 'Tackle',
            'button_idbutton' => $foreignButton->idbutton,
            'character_idcharacter' => $character->idcharacter,
        ])->assertSessionHasErrors();

        $this->assertSame(0, CharacterButtonAlias::count());
    }

    public function test_a_character_button_alias_cannot_point_at_a_character_from_another_game(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->tackleButton($game);
        $foreignCharacter = $this->character($otherGame);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.character-button-aliases.store', $game), [
            'action' => 'Add',
            'alias' => 'Tackle',
            'button_idbutton' => $button->idbutton,
            'character_idcharacter' => $foreignCharacter->idcharacter,
        ])->assertSessionHasErrors();

        $this->assertSame(0, CharacterButtonAlias::count());
    }

    public function test_updating_a_character_button_alias(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->tackleButton($game);
        $otherButton = Button::create(['name' => '5LP', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame]);
        $character = $this->character($game);
        $alias = $character->buttonAliases()->create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.character-button-aliases.store', $game), [
            'action' => 'Update',
            'idcharacterbuttonalias' => $alias->idcharacterbuttonalias,
            'alias' => 'Tackle',
            'button_idbutton' => $otherButton->idbutton,
            'character_idcharacter' => $character->idcharacter,
        ])->assertRedirect(route('admin.character-button-aliases.index', $game));

        $this->assertSame($otherButton->idbutton, $alias->fresh()->button_idbutton);
    }

    public function test_deleting_a_character_button_alias(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->tackleButton($game);
        $character = $this->character($game);
        $alias = $character->buttonAliases()->create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.character-button-aliases.store', $game), [
            'action' => 'Delete',
            'idcharacterbuttonalias' => $alias->idcharacterbuttonalias,
        ])->assertRedirect(route('admin.character-button-aliases.index', $game));

        $this->assertDatabaseMissing('character_button_alias', ['idcharacterbuttonalias' => $alias->idcharacterbuttonalias]);
    }

    public function test_two_aliases_for_the_same_character_cannot_share_an_alias_word(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->tackleButton($game);
        $character = $this->character($game);
        $character->buttonAliases()->create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.character-button-aliases.store', $game), [
            'action' => 'Add',
            'alias' => 'tackle',
            'button_idbutton' => $button->idbutton,
            'character_idcharacter' => $character->idcharacter,
        ])->assertSessionHasErrors();

        $this->assertSame(1, CharacterButtonAlias::where('character_idcharacter', $character->idcharacter)->count());
    }

    public function test_two_different_characters_can_share_an_alias_word(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->tackleButton($game);
        $toph = $this->character($game, 'Toph');
        $aang = $this->character($game, 'Aang');
        $toph->buttonAliases()->create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.character-button-aliases.store', $game), [
            'action' => 'Add',
            'alias' => 'Tackle',
            'button_idbutton' => $button->idbutton,
            'character_idcharacter' => $aang->idcharacter,
        ])->assertRedirect(route('admin.character-button-aliases.index', $game));

        $this->assertDatabaseHas('character_button_alias', ['alias' => 'Tackle', 'character_idcharacter' => $aang->idcharacter]);
    }

    public function test_bulk_update_saves_every_alias(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->tackleButton($game);
        $character = $this->character($game);
        $alias = $character->buttonAliases()->create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.character-button-aliases.bulkUpdate', $game), [
            'aliases' => [
                $alias->idcharacterbuttonalias => [
                    'alias' => 'Rock Slide',
                    'button_idbutton' => $button->idbutton,
                    'character_idcharacter' => $character->idcharacter,
                ],
            ],
        ])->assertRedirect(route('admin.character-button-aliases.index', $game));

        $this->assertSame('Rock Slide', $alias->fresh()->alias);
    }

    public function test_a_non_moderator_cannot_manage_character_button_aliases(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $user = User::create(['nickname' => 'rando', 'password' => 'password123']);

        $this->actingAs($user);

        $this->get(route('admin.character-button-aliases.index', $game))->assertRedirect()->assertSessionHas('error');
    }
}
