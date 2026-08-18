<?php

namespace App\Services;

use App\Models\Game;

/**
 * Replicates legacy's verify_password(): a submitted password is accepted if
 * it matches the game's bcrypt-hashed globalPass, OR it verifies against the
 * bcrypt modPass AND the game isn't in a locked state (complete 2 or -1).
 * Checked inline on every mutating request — there is no persistent
 * "unlocked" session state in this app.
 */
class GamePasswordChecker
{
    public function check(Game $game, ?string $submitted): bool
    {
        if ($submitted === null) {
            return false;
        }

        if ($game->globalPass !== null && password_verify($submitted, $game->globalPass)) {
            return true;
        }

        $isLocked = in_array($game->complete, [2, -1], true);

        return ! $isLocked && password_verify($submitted, $game->modPass);
    }

    /**
     * The game's own settings page (title/patch/image/description/notation)
     * only accepts the exact globalPass, never modPass — matching legacy's
     * game.php, which intentionally has a stricter check than every other
     * edit page.
     */
    public function checkGlobalOnly(Game $game, ?string $submitted): bool
    {
        return $submitted !== null && $game->globalPass !== null && password_verify($submitted, $game->globalPass);
    }
}
