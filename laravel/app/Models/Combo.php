<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Combo extends Model
{
    protected $table = 'combo';

    protected $primaryKey = 'idcombo';

    protected $fillable = [
        'combo', 'comments', 'video', 'user_iduser', 'character_idcharacter',
        'submited', 'damage', 'type', 'verified', 'patch', 'author', 'password',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'submited' => 'datetime',
            'views' => 'integer',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_idcharacter');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_iduser');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'combo_idcombo');
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ListModel::class, 'combo_listing', 'idcombo', 'idlist')
            ->withPivot(['comment', 'list_category_idlist_category']);
    }

    public function listingType(): BelongsTo
    {
        return $this->belongsTo(GameEntry::class, 'type', 'entryid');
    }
}
