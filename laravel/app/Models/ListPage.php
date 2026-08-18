<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListPage extends Model
{
    protected $table = 'list_page';

    protected $primaryKey = 'idListPage';

    protected $fillable = ['Title', 'Description', 'idList', 'order'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(ListModel::class, 'idList');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ListCategory::class, 'idPage');
    }
}
