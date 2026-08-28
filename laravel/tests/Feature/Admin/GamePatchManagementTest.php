<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GamePatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePatchManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsTrustedModerator(Game $game): User
    {
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        return $trusted;
    }

    public function test_adding_a_patch_closes_out_the_previous_current_patch(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);

        $first = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01']);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Add',
            'label' => '1.1',
            'released_at' => '2026-02-01',
        ])->assertRedirect(route('admin.patches.index', $game));

        $first->refresh();
        $this->assertSame('2026-02-01', $first->ended_at->toDateString());

        $newCurrent = GamePatch::where('game_idgame', $game->idgame)->where('label', '1.1')->firstOrFail();
        $this->assertNull($newCurrent->ended_at);
    }

    public function test_adding_a_patch_rejects_an_out_of_order_release_date(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);

        GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-02-01']);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Add',
            'label' => '0.9',
            'released_at' => '2026-01-01',
        ])->assertSessionHasErrors();

        $this->assertDatabaseMissing('game_patches', ['label' => '0.9']);
    }

    public function test_adding_a_patch_rejects_a_duplicate_label(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);

        GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01']);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Add',
            'label' => '1.0',
            'released_at' => '2026-02-01',
        ])->assertSessionHasErrors();

        $this->assertSame(1, GamePatch::where('game_idgame', $game->idgame)->count());
    }

    public function test_updating_the_current_patchs_label_and_date(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);

        $previous = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        $current = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.1', 'released_at' => '2026-02-01']);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Update',
            'idgame_patch' => $current->idgame_patch,
            'label' => '1.1a',
            'released_at' => '2026-02-05',
        ])->assertRedirect(route('admin.patches.index', $game));

        $current->refresh();
        $previous->refresh();
        $this->assertSame('1.1a', $current->label);
        $this->assertSame('2026-02-05', $current->released_at->toDateString());
        $this->assertSame('2026-02-05', $previous->ended_at->toDateString());
    }

    public function test_updating_a_historical_patchs_release_date_is_rejected(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);

        $historical = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.1', 'released_at' => '2026-02-01']);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Update',
            'idgame_patch' => $historical->idgame_patch,
            'label' => '1.0',
            'released_at' => '2025-12-01',
        ])->assertSessionHasErrors();

        $this->assertSame('2026-01-01', $historical->fresh()->released_at->toDateString());
    }

    public function test_renaming_a_historical_patchs_label_is_allowed(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);

        $historical = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Update',
            'idgame_patch' => $historical->idgame_patch,
            'label' => '1.0-final',
        ])->assertRedirect(route('admin.patches.index', $game));

        $this->assertSame('1.0-final', $historical->fresh()->label);
    }

    public function test_deleting_the_current_patch_reopens_the_previous_one_and_reassigns_its_combos(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        $previous = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        $current = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.1', 'released_at' => '2026-02-01']);
        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => 1, 'patch_idgame_patch' => $current->idgame_patch]);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Delete',
            'idgame_patch' => $current->idgame_patch,
        ])->assertRedirect(route('admin.patches.index', $game));

        $this->assertDatabaseMissing('game_patches', ['idgame_patch' => $current->idgame_patch]);
        $this->assertNull($previous->fresh()->ended_at);
        $this->assertSame($previous->idgame_patch, $combo->fresh()->patch_idgame_patch);
    }

    public function test_deleting_a_middle_patch_merges_its_range_and_combos_into_the_previous_patch(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        $previous = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        $middle = GamePatch::create(['game_idgame' => $game->idgame, 'label' => 'junk', 'released_at' => '2026-02-01', 'ended_at' => '2026-03-01']);
        $next = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.1', 'released_at' => '2026-03-01']);

        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => 1, 'patch_idgame_patch' => $middle->idgame_patch]);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Delete',
            'idgame_patch' => $middle->idgame_patch,
        ])->assertRedirect(route('admin.patches.index', $game));

        $this->assertDatabaseMissing('game_patches', ['idgame_patch' => $middle->idgame_patch]);
        $this->assertSame('2026-03-01', $previous->fresh()->ended_at->toDateString());
        // The next patch, and the overall current patch, are untouched.
        $this->assertSame('2026-03-01', $next->fresh()->released_at->toDateString());
        $this->assertNull($next->fresh()->ended_at);
        $this->assertSame($previous->idgame_patch, $combo->fresh()->patch_idgame_patch);
    }

    public function test_deleting_the_earliest_patch_merges_its_range_and_combos_into_the_next_patch(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        $earliest = GamePatch::create(['game_idgame' => $game->idgame, 'label' => 'junk', 'released_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        $next = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-02-01']);

        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => 1, 'patch_idgame_patch' => $earliest->idgame_patch]);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Delete',
            'idgame_patch' => $earliest->idgame_patch,
        ])->assertRedirect(route('admin.patches.index', $game));

        $this->assertDatabaseMissing('game_patches', ['idgame_patch' => $earliest->idgame_patch]);
        $this->assertSame('2026-01-01', $next->fresh()->released_at->toDateString());
        $this->assertSame($next->idgame_patch, $combo->fresh()->patch_idgame_patch);
    }

    public function test_deleting_the_only_patch_leaves_the_game_with_none_and_unlinks_its_combos(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAsTrustedModerator($game);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        $only = GamePatch::create(['game_idgame' => $game->idgame, 'label' => '1.0', 'released_at' => '2026-01-01']);
        $combo = Combo::create(['combo' => 'A', 'character_idcharacter' => $character->idcharacter, 'type' => 1, 'patch_idgame_patch' => $only->idgame_patch]);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Delete',
            'idgame_patch' => $only->idgame_patch,
        ])->assertRedirect(route('admin.patches.index', $game));

        $this->assertSame(0, GamePatch::where('game_idgame', $game->idgame)->count());
        $this->assertNull($combo->fresh()->patch_idgame_patch);
    }

    public function test_non_trusted_user_cannot_manage_patches(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123']));

        $this->get(route('admin.patches.index', $game))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Add',
            'label' => '1.0',
            'released_at' => '2026-01-01',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('game_patches', ['game_idgame' => $game->idgame]);
    }

    public function test_patches_cannot_be_managed_across_games(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $foreignPatch = GamePatch::create(['game_idgame' => $otherGame->idgame, 'label' => '1.0', 'released_at' => '2026-01-01']);

        $this->actingAsTrustedModerator($game);

        $this->post(route('admin.patches.store', $game), [
            'action' => 'Delete',
            'idgame_patch' => $foreignPatch->idgame_patch,
        ]);

        $this->assertDatabaseHas('game_patches', ['idgame_patch' => $foreignPatch->idgame_patch]);
    }
}
