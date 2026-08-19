<?php

namespace App\Policies;

use App\Models\ListModel;
use App\Models\User;

class ListPolicy
{
    public function update(User $user, ListModel $list): bool
    {
        return $user->isTrusted() || $user->iduser === $list->user_iduser;
    }

    public function delete(User $user, ListModel $list): bool
    {
        return $user->isTrusted() || $user->iduser === $list->user_iduser;
    }
}
