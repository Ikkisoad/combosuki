<?php

namespace App\Policies;

use App\Models\Combo;
use App\Models\User;

class ComboPolicy
{
    public function update(User $user, Combo $combo): bool
    {
        return $user->isTrusted() || $user->iduser === $combo->user_iduser;
    }

    public function delete(User $user, Combo $combo): bool
    {
        return $user->isTrusted() || $user->iduser === $combo->user_iduser;
    }

    public function verify(User $user, Combo $combo): bool
    {
        return $user->isTrusted();
    }
}
