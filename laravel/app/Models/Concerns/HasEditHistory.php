<?php

namespace App\Models\Concerns;

use App\Models\EditHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEditHistory
{
    public function editHistories(): MorphMany
    {
        return $this->morphMany(EditHistory::class, 'editable')->latest('id');
    }

    public function recordEdit(string $action = 'updated'): EditHistory
    {
        return $this->editHistories()->create([
            'user_iduser' => auth()->id(),
            'action' => $action,
        ]);
    }
}
