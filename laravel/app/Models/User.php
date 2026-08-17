<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $table = 'user';

    protected $primaryKey = 'iduser';

    protected $fillable = ['nickname', 'trusted_user'];

    public function combos(): HasMany
    {
        return $this->hasMany(Combo::class, 'user_iduser');
    }
}
