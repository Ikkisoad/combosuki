<?php

namespace Tests\Feature\Admin;

use App\Models\Button;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ButtonBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_edit_form_renders_inputs_for_every_button_bound_to_the_shared_form(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $buttonA = Button::create(['name' => 'L', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        $buttonB = Button::create(['name' => 'M', 'color' => '#000000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $response = $this->get(route('admin.buttons.index', $game));

        $response->assertOk();
        $response->assertSee('id="bulk-buttons-form"', false);
        $response->assertSee("buttons[{$buttonA->idbutton}][name]", false);
        $response->assertSee("buttons[{$buttonB->idbutton}][name]", false);
        $response->assertSee('Save All');
    }

    public function test_bulk_update_saves_every_submitted_button_in_one_request(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $buttonA = Button::create(['name' => 'L', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        $buttonB = Button::create(['name' => 'M', 'color' => '#000000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $response = $this->post(route('admin.buttons.bulkUpdate', $game), [
            'buttons' => [
                $buttonA->idbutton => ['name' => 'LP', 'color' => '#111111', 'match_type' => 'starts_with', 'order' => 5],
                $buttonB->idbutton => ['name' => 'MP', 'color' => '#222222', 'match_type' => 'ends_with', 'order' => 6],
            ],
        ]);

        $response->assertRedirect(route('admin.buttons.index', $game));

        $this->assertDatabaseHas('button', [
            'idbutton' => $buttonA->idbutton,
            'name' => 'LP',
            'color' => '#111111',
            'match_type' => 'starts_with',
            'order' => 5,
        ]);
        $this->assertDatabaseHas('button', [
            'idbutton' => $buttonB->idbutton,
            'name' => 'MP',
            'color' => '#222222',
            'match_type' => 'ends_with',
            'order' => 6,
        ]);
    }

    public function test_bulk_update_cannot_touch_buttons_belonging_to_another_game(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $foreignButton = Button::create(['name' => 'HP', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $otherGame->idgame, 'order' => 1]);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $this->post(route('admin.buttons.bulkUpdate', $game), [
            'buttons' => [
                $foreignButton->idbutton => ['name' => 'HACKED', 'color' => '#000000', 'match_type' => 'exact', 'order' => 1],
            ],
        ]);

        $this->assertSame('HP', $foreignButton->fresh()->name);
    }
}
