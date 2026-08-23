<?php

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;

class MatchPolicy
{
    public function update(User $user, GameMatch $match): bool
    {
        return $user->isTrusted() || $user->iduser === $match->user_iduser;
    }

    public function delete(User $user, GameMatch $match): bool
    {
        return $user->isTrusted() || $user->iduser === $match->user_iduser;
    }
}
