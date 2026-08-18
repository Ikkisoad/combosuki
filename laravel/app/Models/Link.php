<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Link extends Model
{
    protected $table = 'link';

    protected $primaryKey = 'idLink';

    protected $fillable = ['idGame', 'Title', 'Link'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'idGame');
    }
}
