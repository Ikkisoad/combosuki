<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListCategory extends Model
{
    protected $table = 'list_category';

    protected $primaryKey = 'idlist_category';

    protected $fillable = ['title', 'list_idlist', 'order', 'idPage'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(ListModel::class, 'list_idlist');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(ListPage::class, 'idPage');
    }
}
