<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    public function create(User $user): bool
    {
        return $user->isTrusted();
    }

    /**
     * Unrestricted for admins; everyone else (including trusted, non-admin
     * users) needs an explicit assignment via the game_moderator pivot,
     * which a game's creator gets automatically (see GameController::store).
     */
    public function update(User $user, Game $game): bool
    {
        return $user->is_admin || $user->moderatedGames()->where('game.idgame', $game->idgame)->exists();
    }

    /**
     * No bypass for trusted users or assigned moderators — deleting a game
     * is admin-only, even for its own creator.
     */
    public function delete(User $user, Game $game): bool
    {
        return (bool) $user->is_admin;
    }
}
